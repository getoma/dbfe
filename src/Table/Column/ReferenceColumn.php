<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Generator\Field\SelectField;
use getoma\dbfe\Form\Generator\Node\NodeInterface;
use getoma\dbfe\Table\Table;
use getoma\dbfe\Util\Exception\DatabaseStructureIssue;

use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\QueryFactory;

/**
 * a db table column which references another table via foreign key constraint
 */
class ReferenceColumn extends PlainColumn implements ReferenceColumnIf
{
   protected array|SelectInterface $query;
   protected array|SelectInterface $disabledKeys = [];

   public function __construct(
      PlainColumn|array $structure,
      string $table,
      protected readonly Table $refTable,
      private readonly \PDO $dbh,
      private readonly QueryFactory $query_factory,
   )
   {
      parent::__construct( $structure, $table, false );
   }

   /**
    */
   public function getTable(): Table
   {
      return $this->refTable;
   }

   /**
    */
   public function getReferenceData( array $filter = [] ): array
   {
      if( !isset($this->query) )
      {
         // get the content of the other table, select id and name col
         $idCol = $this->refTable->getIdColumn() ?? throw new DatabaseStructureIssue("{$this->refTable->getName()} does not have an ID column.");
         $nameCol = $this->refTable->getNameColumn() ?? throw new DatabaseStructureIssue("{$this->refTable->getName()} does not have a Name column.");
         $this->query = $this->query_factory->newSelect()
            ->cols([ $idCol->getName(), $nameCol->getName() ])
            ->from($this->getTable()->getName());
         foreach( $filter as $col => $value )
         {
            $this->query->where("$col=:$col", [$col => $value]);
         }
      }

      if( $this->query instanceof SelectInterface )
      {
         $stmt = $this->dbh->prepare( $this->query );
         $stmt->execute($this->query->getBindValues());
         $refValues = [];
         while( $row = $stmt->fetch(\PDO::FETCH_NUM) )
         {
            $refValues[$row[0]] = $row[1];
         }
         return $refValues;
      }
      else if( is_array($this->query) )
      {
         // data is given directly already
         return $this->query;
      }
      else
      {
         throw new \LogicException("unsupported type of reference query for column " . $this->getName() );
      }
   }

   /**
    * get a list of "disabled" selection keys
    */
   private function getDisabledKeys()
   {
      $query = $this->disabledKeys;
      if( $query instanceof SelectInterface )
      {
         $stmt = $this->dbh->prepare($query->getStatement());
         $stmt->execute($query->getBindValues());
         return $stmt->fetchAll(\PDO::FETCH_COLUMN);
      }
      else if( is_array($query) )
      {
         // data is given directly already
         return $query;
      }
      else
      {
         throw new \LogicException('invalid internal type');
      }
   }

   public function getFormGeneratorDefinition(): NodeInterface
   {
      return new SelectField(
         $this->getAffixedName(),
         $this->getReferenceData(),
         $this->getDisabledKeys(),
         $this->isRequired(),
         $this->isFixed(),
      );
   }

   /**
    * set a customized array to retrieve the selection data set
    * to set the reference content
    * @param SelectInterface|array $query        - the query (or prepared dataset)
    * @param SelectInterface|array $disable_keys - any entry that shall no longer be selectable (unless they are already used for a specific field)
    */
   public function setReferenceQuery( SelectInterface|array $query, SelectInterface|array|null $disable_keys = null ): void
   {
      $this->query = $query;
      if( isset($disable_keys) ) $this->disabledKeys = $disable_keys;
   }
}

<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Printer\Configuration\Configuration;
use getoma\dbfe\Form\Printer\Configuration\ConfigurationIf;
use getoma\dbfe\Form\Printer\Configuration\ConfigurationListIf;
use getoma\dbfe\Table\Table;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use getoma\dbfe\Util\QueryBuilder\SelectQuery;
use getoma\dbfe\Util\Exception\DatabaseStructureIssue;

/**
 * a db table column which references another table via foreign key constraint
 */
class ReferenceColumn extends PlainColumn implements ReferenceColumnIf
{
   protected array|SelectQuery $query;
   protected array|SelectQuery $disabledKeys = [];

   public function __construct(
      PlainColumn|array $structure,
      string $table,
      protected readonly Table $refTable)
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
   public function getReferenceData( array $filter = [], bool $addNA = true ): array
   {
      if( !isset($this->query) )
      {
         // get the content of the other table, select id and name col
         $idCol = $this->refTable->getIdColumn() ?? throw new DatabaseStructureIssue("{$this->refTable->getName()} does not have an ID column.");
         $nameCol = $this->refTable->getNameColumn() ?? throw new DatabaseStructureIssue("{$this->refTable->getName()} does not have a Name column.");
         $this->query = new SelectQuery();
         $this->query->columns    = [ $idCol->getName(), $nameCol->getName() ];
         $this->query->filter     = $filter;
      }

      if( $this->query instanceof SelectQuery )
      {
         $refData = $this->refTable->query( $this->query );

         $refValues = [];
         if( $addNA )
         {
            $refValues[''] = "N/A";
         }

         // add the content of the referenced column to the selectable data
         if( $refData )
         {
            while( $row = $refData->fetch( \PDO::FETCH_NUM ) )
            {
               $refValues[$row[0]] = $row[1];
            }
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
      if( $query instanceof SelectQuery )
      {
         return $this->refTable->query( $query )->fetchAll(\PDO::FETCH_COLUMN);
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

   /**
    * get a form specification that can be used as input to Form\Printer
    * A "Reference Column" is implemented by providing a select field which
    * allows to select an entry of the reference column.
    * The selectable values are taken from the "name column" of the other table.
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false ): ConfigurationIf|ConfigurationListIf
   {
      return new Configuration(
         [ 'name'  => $this->getAfixedName($as_array)
         , 'label' => $lblHdl->get( $this->getName(), $this->tablename )
         , 'required' => ($this->isRequired() && !$as_array)
         , 'fixed'    => $this->isFixed()
         , 'type'  => 'select', 'selection' => $this->getReferenceData(), 'disabled_keys' => $this->getDisabledKeys() ] );
   }

   /**
    * set a customized array to retrieve the selection data set
    * to set the reference content
    * @param SelectQuery|array $query
    * @param SelectQuery|array $disable_keys - any keys that shall no longer be selectable (unless they are already used for a specific field)
    */
   public function setReferenceQuery( SelectQuery|array $query, SelectQuery|array|null $disable_keys = null ): void
   {
      $this->query = $query;
      if( isset($disable_keys) ) $this->disabledKeys = $disable_keys;
   }
}

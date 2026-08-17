<?php

namespace getoma\dbfe\Table;

use getoma\dbfe\Form\Generator\Field\CellField;
use getoma\dbfe\Form\Generator\Node\CompositeNode;
use getoma\dbfe\Form\Generator\Node\NodeInterface;
use getoma\dbfe\Form\Generator\Node\TableNode;
use getoma\dbfe\Table\Column\ColumnIf;
use getoma\dbfe\Table\Column\FileContentType;
use getoma\dbfe\Table\Column\PlainColumn;
use getoma\dbfe\Util\FileHandler\FileHandlerIf;
use getoma\dbfe\Util\ValidatedInput;

use Aura\SqlQuery\Common\SelectInterface;

class View implements TableIf
{
   /** @var ViewColumn[] */
   protected array $m_columns;

   function __construct(
      protected readonly string $name,
      protected readonly SelectInterface $query,
      protected readonly \PDO $dbh
   )
   {
      $first = true;
      foreach( $query->getCols() as $alias => $col )
      {
         /** retrieve explicit alias of column (either already in alias, or extract from column spec) */
         if( is_numeric($alias) )
         {
            $alias = preg_match( '/([a-zA-Z0-9_]+)["\']? *$/', $col, $matches )? $matches[1] : $col;
         }
         $this->m_columns[$alias] = new ViewColumn($alias, $name, $first);
         $first = false;
      };
   }

   public function linkReferences( Factory $factory, int $options = 0 ): void
   {
      // N/A
   }

   public function getName(): string
   {
      return $this->name;
   }

   public function getColumns(): array
   {
      return $this->m_columns;
   }

   public function getColumn(string $name): ColumnIf
   {
      return $this->m_columns[$name];
   }

   public function getColumnCount(): int
   {
      return count($this->query->columns);
   }

   public function getNameColumn(): ColumnIf
   {
      throw new \LogicException('getNameColumn() for View not supported, yet!');
   }

   public function getIdColumn(): ?ColumnIf
   {
      return null;
   }

   public function getNonIdColumns(): array
   {
      return $this->getColumns();
   }

   /**
    * get all primary key columns
    */
   public function getPrimaryKey(): array
   {
      return [];
   }

   /**
    * get all columns EXCEPT primary key columns
    * @return ColumnIf[]
    */
   public function getNonKeyColumns(): array
   {
      return $this->getColumns();
   }

   public function hasExternalReferences(): bool
   {
      return false;
   }

   public function getExternalReferences(): array
   {
      return [];
   }

   public function registerReference(TableReference $ref)
   {
      throw new \LogicException("Views can't have references!");
   }

   public function setOrdering(string|array $order): void
   {
      $this->query->order = $order;
   }

   public function hasId(int $id): bool
   {
      throw new \LogicException('check for id not supported, yet!');
   }

   public function setFilehandler( string $column, FileHandlerIf $fh, FileContentType $content_type = FileContentType::Opaque, bool $support_delete = false ): void
   {
      throw new \LogicException("Views can't have uploads!");
   }

   public function setValueSelection(string $column, array|SelectInterface $selection): void
   {
      throw new \LogicException("Views can't have value selections!");
   }

   public function hasUploads(): bool
   {
      return false;
   }

   public function getFormGeneratorDefinition(
      bool $as_array = false,
      bool $required_only = false,
      array $groups = [],
      ?string $refcol = null,
   ): CompositeNode
   {
      $table = new TableNode($this->getName());

      foreach( $this->getColumns() as $colname => $col )
      {
         if( $colname === $refcol )                  continue;
         if( $required_only && !$col->isRequired() ) continue;
         if( $col->doSkip() )                        continue;

         $table->add($col->getFormGeneratorDefinition());
      }

      return $table;
   }

   public function getFormData(int|string|array $selector = [], bool $as_array = false): array
   {
      $result = [];

      /* build query to retrieve the contents of this table */
      $query = clone $this->query;
      /* add row selection */
      if( is_array($selector) )
      {
         foreach( $selector as $key => $value )
         {
            $query->where("$key=?", [$value]);
         }
      }
      else
      {
         throw new \LogicException( "invalid selector $selector" );
      }

      /* execute query */
      $stmt = $this->dbh->prepare( $query->getStatement() );
      $stmt->execute($query->getBindValues());
      $first = true;

      while( $row = $stmt->fetch(\PDO::FETCH_ASSOC) )
      {
         if( !$first && !$as_array ) throw new \LogicException("received multiple datasets for non-array request");

         foreach( $row as $name => $value )
         {
            if( $as_array )
            {
               $result[PlainColumn::affixedName($name, $this->getName())][] = $value;
            }
            else
            {
               $result[PlainColumn::affixedName($name, $this->getName())] = $value;
            }
         }

         $first = false;
      }

      return $result;
   }

   public function getFormValidation(bool $skip_auto_increment = false, bool $as_array = false, $skip = []): array
   {
      return [];
   }

   public function insertData(ValidatedInput $data, bool $updateOnDuplicate = false, array $reference_filter = []): void
   {
      /* nothing to do */
   }

   public function dropRowset(array $id_columns, array $id_values): void
   {
      /* nothing to do */
   }

   public function lastInsertId(): ?int
   {
      return null;
   }

   public function deleteRowsFromFv(ValidatedInput $data, array $reference_filter = []): void
   {
      /* nothing to do */
   }

   public function dropRow(mixed $identifier): void
   {
      /* nothing to do */
   }

   public function updateRow(ValidatedInput $data, mixed $identifier): void
   {
      /* nothing to do */
   }
}

class ViewColumn implements ColumnIf
{
   /** @var string */
   protected $name;

   /** @var string */
   protected $view_name;

   /**  */
   protected array $m_formProp = [];

   /** @var bool */
   protected $skip = false;

   /** @var bool */
   protected $isKey;

   function __construct( string $name, string $view_name, bool $isKey = false )
   {
      $this->name      = $name;
      $this->view_name = $view_name;
      $this->isKey     = $isKey;
   }

   public function getFormGeneratorDefinition(): NodeInterface
   {
      return new CellField($this->getAffixedName());
   }

   public function isRequired(): bool
   {
      return false;
   }

   public function isAutoIncrement(): bool
   {
      return false;
   }

   public function doSkip(?bool $status = null): bool
   {
      if( isset($status) ) $this->skip = $status;
      return $this->skip;
   }

   public function getName(): string
   {
      return $this->name;
   }

   public function getType(): string
   {
      return 'Cell';
   }

   public function sqlColumnSpec(?string $spec = null): string
   {
      throw new \LogicException("sqlColumnSpec not supported for View Column");
   }

   public function addFormProperties(array $prop): void
   {
      $this->m_formProp += $prop;
   }

   public function getValidatorConfig(bool $as_array = false, bool $optional = false): \Respect\Validation\Validator
   {
      throw new \LogicException("validator config not supported for View Column");
   }

   public function isUnique(): bool
   {
      return false;
   }

   public function getDefault(): mixed
   {
      return null;
   }

   public function isPrimaryKey(): bool
   {
      return $this->isKey;
   }

   public function isFixed(): bool
   {
      return true;
   }

   public function getAffixedName(): string
   {
      /* no specific array support for view, this is only needed for <input> names */
      return PlainColumn::affixedName($this->name, $this->view_name);
   }

   public function setCustomConstraint(\Respect\Validation\Validator $constraint): void
   {
      // nothing to do
   }

   public function makeRequired(): void
   {
      // yeah, whatever...
   }

   public function makeFixed(): void
   {

   }
}

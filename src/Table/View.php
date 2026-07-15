<?php

namespace getoma\dbfe\Table;

use getoma\dbfe\Form\Printer\Configuration\ConfigurationListIf;
use getoma\dbfe\Table\Column\ColumnIf;
use getoma\dbfe\Table\Column\DispType;
use getoma\dbfe\Table\Column\PlainColumn;
use getoma\dbfe\Util\FileHandler\FileHandlerIf;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;

use Aura\SqlQuery\Common\SelectInterface;

class View implements TableIf
{
   /** @var ViewColumn[] */
   protected array $m_columns;

   /* default setting for "hideEmpty" */
   public static $HIDE_EMPTY = false;

   /* hide empty */
   protected bool $m_hide_empty;

   function __construct(
      protected readonly string $name,
      protected readonly SelectInterface $query,
      protected readonly \PDO $dbh
   )
   {
      $this->m_hide_empty = self::$HIDE_EMPTY;

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

   public function hideEmpty( bool $hide )
   {
      $this->m_hide_empty = $hide;
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

   public function useFieldsetsForReferences(?bool $status = null): bool
   {
      /* nothing to do */
      return false;
   }

   public function setOrdering(string|array $order): void
   {
      $this->query->order = $order;
   }

   public function hasId(int $id): bool
   {
      throw new \LogicException('check for id not supported, yet!');
   }

   public function setFilehandler( string $column, FileHandlerIf $fh, DispType $display_type = DispType::link, bool $support_delete = false ): void
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

   /**
    * {@inheritDoc}
    * @see \getoma\dbfe\Table\TableIf::getFormDefinition()
    * @return ConfigurationListIf
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data, array $options = []): ConfigurationListIf
   {
      if( !$data[$this->getName() . "___empty"] || !$this->m_hide_empty )
      {
         $content = array_map( function($col) use ($lblHdl, $data, $options)
                               {
                                  return $col->getFormDefinition( $lblHdl, $data, $options['as_array'] || false );
                               },
                              // skip first column in output (contains link to main table)
                              array_slice( array_values( $this->getColumns() ), 1 ) );

         return new \getoma\dbfe\Form\Printer\Configuration\ConfigurationList( [ [ 'type' => 'table', 'content' => $content ] ] );
      }
      else
      {
         return new \getoma\dbfe\Form\Printer\Configuration\ConfigurationList();
      }
   }

   public function getFormData(int|string|array $selector = []): array
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

      $result[$this->getName() . "___empty"] = ($stmt->rowCount() === 0);

      while( $row = $stmt->fetch(\PDO::FETCH_ASSOC) )
      {
         foreach( $row as $name => $value )
         {
            $result[PlainColumn::afixedName($name, $this->getName())][] = $value;
         }
      }

      return $result;
   }

   public function getFormValidation(bool $skip_auto_increment = false, bool $as_array = false, $skip = []): \getoma\dbfe\Form\Validator\Profile
   {
      return new \getoma\dbfe\Form\Validator\Profile();
   }

   public function insertData(array $data, bool $updateOnDuplicate = false): void
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

   public function deleteRowsFromFv(array $data): void
   {
      /* nothing to do */
   }

   public function dropRow(mixed $identifier): void
   {
      /* nothing to do */
   }

   public function updateRow(array $data, mixed $identifier): void
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

   /**
    * {@inheritDoc}
    * @see \dbfe\ColumnIf::getFormDefinition()
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false): \getoma\dbfe\Form\Printer\Configuration\Configuration
   {
      return new \getoma\dbfe\Form\Printer\Configuration\Configuration(
         array_merge( [ 'name'  => $this->getAfixedName($as_array),
                        'label' => $lblHdl->get( $this->getName(), $this->view_name ),
                        'type'  => 'Cell' ],
                        $this->m_formProp ) );
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

   public function getValidatorConfig(bool $as_array = false): \getoma\dbfe\Form\Validator\Profile
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

   public function getAfixedName(bool $as_array = false): string
   {
      /* no specific array support for view, this is only needed for <input> names */
      return PlainColumn::afixedName($this->name, $this->view_name, false);
   }

   public function setCustomConstraint(\getoma\dbfe\Form\Validator\Constraint\Constraint $constraint): void
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

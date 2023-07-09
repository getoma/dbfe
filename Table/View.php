<?php namespace dbfe;

require_once 'dbfe/Table/Table.php';
require_once 'dbfe/Util/Query.php';

class View implements TableIf
{
   /**@var string */
   protected $name;

   /**@var SelectQuery */
   protected $query;

   /**@var \PDO */
   protected $m_dbh;

   /**@var array[ViewColumn] */
   protected $m_columns;
   
   /**@var Boolean  default setting for "hideEmpty" */
   public static $HIDE_EMPTY = false;
   
   /**@var Boolean  hide empty */
   protected $m_hide_empty;

   function __construct(string $name, SelectQuery $query, \PDO $dbh )
   {
      $this->name  = $name;
      $this->query = $query;
      $this->m_dbh = $dbh;
      
      $this->m_hide_empty = self::$HIDE_EMPTY;

      $first = true;
      foreach( $query->columns as $col )
      {
         /** check if there's an explicit name set for this column */
         /** @var array $matches */
         if( preg_match( '/([a-zA-Z0-9_]+)["\']? *$/', $col, $matches ) )
         {
            $col = $matches[1];
         }
         $this->m_columns[$col] = new ViewColumn($col, $name, $first);
         $first = false;
      };
   }
   
   public function hideEmpty( bool $hide )
   {
      $this->m_hide_empty = $hide;
   }

   public function linkReferences( Factory $factory, $options = 0 )
   {
      // N/A
   }

   public function getName()
   {
      return $this->name;
   }

   public function getColumns()
   {
      return $this->m_columns;
   }

   public function getColumn(string $name)
   {
      return $this->m_columns[$name];
   }

   public function getColumnCount()
   {
      return count($this->query->columns);
   }

   public function getNameColumn()
   {
      throw new \LogicException('getNameColumn() for View not supported, yet!');
   }

   public function getIdColumn()
   {
      return null;
   }

   public function getNonIdColumns()
   {
      return $this->getColumns();
   }

   /**
    * get all primary key columns
    * @return ViewColumn
    */
   public function getPrimaryKey()
   {
      return [];
   }

   /**
    * get all columns EXCEPT primary key columns
    * @return ColumnIf
    */
   public function getNonKeyColumns()
   {
      return $this->getColumns();
   }

   public function hasExternalReferences()
   {
      return false;
   }

   public function getExternalReferences()
   {
      return [];
   }

   public function registerReference(TableReference $ref)
   {
      throw new \LogicException("Views can't have references!");
   }

   public function useFieldsetsForReferences(bool $status = null)
   {
      /* nothing to do */
   }

   public function setOrdering($order)
   {
      $this->query->order = $order;
   }

   public function hasId(int $id)
   {
      throw new \LogicException('check for id not supported, yet!');
   }

   public function setFilehandler( string $column, FileHandlerIf $fh, int $display_type = DispType::link, bool $support_delete = false )
   {
      throw new \LogicException("Views can't have uploads!");
   }

   public function setValueSelection(string $column, $selection)
   {
      throw new \LogicException("Views can't have value selections!");
   }

   public function hasUploads()
   {
      return false;
   }

   /**
    * {@inheritDoc}
    * @see \dbfe\TableIf::getFormDefinition()
    * @return Form\Printer\ConfigurationListIf
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data, array $options = [])
   {
      if( !$data[$this->getName() . "___empty"] || !$this->m_hide_empty )
      {
         $content = array_map( function($col) use ($lblHdl, $data, $options)
                               {
                                  return $col->getFormDefinition( $lblHdl, $data, $options['as_array'] || false );
                               }, 
                              // skip first column in output (contains link to main table)
                              array_slice( array_values( $this->getColumns() ), 1 ) );        
      
         return new Form\Printer\ConfigurationList( [ [ 'type' => 'table', 'content' => $content ] ] );
      }
      else
      {
         return new Form\Printer\ConfigurationList();
      }
   }

   public function getFormData($selector = [])
   {
      $result = [];

      /* build query to retrieve the contents of this table */
      $query = clone $this->query;
      /* add row selection */
      if( is_array($selector) )       $query->filter = array_merge( $query->filter, $selector );
      else if( is_scalar($selector) ) $query->filter[$this->getPrimaryKeyWithCheck()] = $selector;
      else throw new \LogicException( "invalid selector $selector" );

      /* execute query */
      $data = $this->m_dbh->query( $query->asString() );
      if( !$data ) throw new DatabaseError($this->m_dbh->errorCode());
      
      $result[$this->getName() . "___empty"] = ($data->rowCount() === 0);
      
      while( $row = $data->fetch(\PDO::FETCH_ASSOC) )
      {
         foreach( $row as $name => $value )
         {
            $result[PlainColumn::afixedName($name, $this->getName())][] = $value;
         }
      }

      return $result;
   }

   public function query(SelectQuery $query)
   {
      throw new \LogicException('custom query for View not supported!');
   }

   public function getFormValidation(bool $skip_auto_increment = false, bool $as_array = false, $skip = [])
   {
      return null;
   }

   public function insertData(array $data, bool $updateOnDuplicate = false)
   {
      /* nothing to do */
   }

   public function dropRowset(array $id_columns, array $id_values)
   {
      /* nothing to do */
   }

   public function lastInsertId()
   {
      return null;
   }

   public function deleteRowsFromFv(array $data)
   {
      /* nothing to do */
   }

   public function dropRow($identifier)
   {
      /* nothing to do */
   }

   public function updateRow(array $data, $identifier)
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

   /** @var array[mixed] */
   protected $m_formProp = [];

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
    * @return \dbfe\Form\Printer\Configuration
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false)
   {
      return new Form\Printer\Configuration( 
         array_merge( [ 'name'  => $this->getAfixedName($as_array),
                        'label' => $lblHdl->get( $this->getName(), $this->view_name ),
                        'type'  => 'Cell' ],
                        $this->m_formProp ) );
   }

   public function isRequired()
   {
      return false;
   }

   public function isAutoIncrement()
   {
      return false;
   }

   public function doSkip(bool $status = null)
   {
      if( isset($status) ) $this->skip = $status;
      return $this->skip;
   }

   public function getName()
   {
      return $this->name;
   }
   
   public function getType()
   {
      return 'Cell';
   }

   public function sqlColumnSpec($spec = null)
   {
      throw new \LogicException("sqlColumnSpec not supported for View Column");
   }

   public function addFormProperties(array $prop)
   {
      $this->m_formProp += $prop;
   }

   public function getValidatorConfig(bool $as_array = false)
   {
      return null;
   }

   public function isUnique()
   {
      return false;
   }

   public function getDefault()
   {
      return null;
   }

   public function isPrimaryKey()
   {
      return $this->isKey;
   }
   
   public function isFixed()
   {
      return true;
   }

   public function getAfixedName(bool $as_array = false)
   {
      /* no specific array support for view, this is only needed for <input> names */
      return PlainColumn::afixedName($this->name, $this->view_name, false);
   }

   public function setCustomConstraint(Form\Validator\Constraint $constraint)
   {
      // nothing to do
   }

   public function makeRequired()
   {
      // yeah, whatever...
   }
   
   public function makeFixed()
   {
      
   }
}

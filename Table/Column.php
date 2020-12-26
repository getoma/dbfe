<?php namespace dbfe;

require_once 'dbfe/Table/ColumnTypes.php';
require_once 'dbfe/Util/HtmlElement.php';
require_once 'dbfe/Util/LabelHandler.php';

require_once 'dbfe/Form/Validator/Constraint.php';
require_once 'dbfe/Form/Printer/Configuration.php';

interface ColumnIf
{
   /**
    * add properties to the html output for this column 
    * @param array $prop
    */
   public function addFormProperties(array $prop);

   /**
    * get a form specification that can be used as input to Form\Printer
    * @return \dbfe\Form\Printer\Configuration|\dbfe\Form\Printer\ConfigurationList
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false);

   /**
    * get the column name
    * @return string
    */
   public function getName();
   
   /**
    * get the type of the column
    * @return string
    */
   public function getType();

   /**
    * get or set column specifier to use in "select" query
    * get current column specifier
    * @param string $spec
    * @return string
    */
   public function sqlColumnSpec($spec = null);

   /**
    * get the default value of the column
    * @return mixed
    */
   public function getDefault();

   /**
    * whether this field needs to be filled with a value when writing to the DB
    * It is required if the column is "not null" AND there is no default value
    * @return boolean
    */
   public function isRequired();

   /**
    * @return boolean
    */
   public function isAutoIncrement();

   /**
    * @return boolean
    */
   public function isPrimaryKey();

   /**
    * @return boolean
    */
   public function isUnique();

   /**
    * @return string
    */
   public function getAfixedName( bool $as_array = false );

   /**
    * provide column specific form\validator configuration
    * @return Form\Validator\Profile
    */
   public function getValidatorConfig(bool $as_array = false);

   /**
    * whether this column shall be skipped in the processing
    * return current status of skipping
    * @return bool
    */
   public function doSkip(bool $status = null);

   /**
    * add a custom constraint from the application
    */
   public function setCustomConstraint( Form\Validator\Constraint $constraint );

   /**
    * make a column required although the database itself allows NULL values
    */
   public function makeRequired();
}

interface ReferenceColumnIf extends ColumnIf
{
   /** set a customized array to retrieve the selection data set
    *  to set the reference content
    * @param SelectQuery $query
    */
   public function setReferenceQuery( SelectQuery $query );

   /**
    * get the content of the reference selection
    * @return array
    */
   public function getReferenceData();
   
   /**
    * get the table name of this reference
    * @return Table
    */
   public function getTable();
}

interface FileHandlerColumnIf extends ColumnIf
{
   /**
    * perform the upload of a file
    * @param array $data
    * @param string $rowid
    */
   public function handleUpload( array &$data, $rowid);

   /**
    * drop the given files
    * @param array $files
    */
   public function dropFiles( array $files );
}

/**
 * a plain db table column
 */
class PlainColumn implements ColumnIf
{
   /** @var string */
   protected $m_name;

   /** @var Type */
   protected $m_type;

   /** @var string */
   protected $m_key;

   /** @var string */
   protected $m_extra;

   /** @var mixed */
   protected $m_default;

   /** @var array[mixed] */
   protected $m_formProp = [];

   /** @var string */
   protected $m_tablename = null;

   /** @var bool */
   protected $skip = false;

   /** @var string */
   protected $m_column_spec = "%s";

   /** @var Form\Validator\Constraint */
   protected $m_custom_constraint = null;

   /** @var bool */
   protected $m_required = false;

   public function __construct( $structure, string $table, bool $heuristic_types = false)
   {
      if( $structure instanceof PlainColumn )
      {
         /* copy constructor */
         $this->m_name    = $structure->m_name;
         $this->m_type    = clone $structure->m_type;
         $this->m_extra   = $structure->m_extra;
         $this->m_default = $structure->m_default;
         $this->m_key     = $structure->m_key;
      }
      else if( is_array($structure) )
      {
         /* construction from structure definition */
         $this->m_name    = $structure["Field"];
         $this->m_type    = Type::create( $structure["Type"], $structure["Null"], $structure["Field"], $heuristic_types );
         $this->m_extra   = $structure["Extra"];
         $this->m_default = $structure["Default"] == "NULL" ? null : $structure["Default"];
         $this->m_key     = $structure["Key"];
      }
      else
      {
         throw new \LogicException('unsupported type for structure in column construction');
      }
      $this->m_tablename = $table;
   }

   public function addFormProperties(array $prop)
   {
      $this->m_formProp += $prop;
   }

   /**
    * add a custom constraint from the application
    */
   public function setCustomConstraint( Form\Validator\Constraint $constraint )
   {
      $this->m_custom_constraint = $constraint;
   }

   /**
    * get a form specification that can be used as input to Form\Printer
    * @return Form\Printer\Configuration
    * 
    * {@inheritDoc}
    * @see \dbfe\ColumnIf::getFormDefinition()
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false)
   {
      return new Form\Printer\Configuration( 
         array_merge( [ 'name'     => $this->getAfixedName($as_array),
                        'label'    => $lblHdl->get( $this->getName(), $this->m_tablename ),
                        'required' => $this->isRequired() && !$as_array ],
                            $this->m_formProp,
                            $this->m_type->getFormAttributes( $lblHdl, $this->m_tablename . '.' . $this->getName() )
            ) );
   }

   /**
    * column name
    *
    * @return string
    */
   public function getName()
   {
      return $this->m_name;
   }
   
   /**
    * {@inheritDoc}
    * @see \dbfe\ColumnIf::getType()
    */
   public function getType()
   {
      $class = get_class($this->m_type);
      return ($pos = strrpos($class, '\\'))? substr($class, $pos + 1) : $class;
   }

   /**
    * get or set column specifier to use in "select" query
    * @param string $spec
    */
   public function sqlColumnSpec($spec = null)
   {
      if( isset($spec) ) $this->m_column_spec = $spec;
      return sprintf($this->m_column_spec, $this->getName())." ".$this->getName();
   }

   /**
    * default value of column
    *
    * @return mixed
    */
   public function getDefault()
   {
      return $this->m_type->getDefault() ?? $this->m_default;
   }

   /**
    * whether this field needs to be filled with a value when writing to the DB
    * It is required if the column is "not null" AND there is no default value
    *
    * @return boolean
    */
   public function isRequired()
   {
      return $this->m_required || (!$this->m_type->isNullOk() && !isset( $this->m_default ));
   }

   /**
    * @return boolean
    */
   public function isAutoIncrement()
   {
      return strpos( $this->m_extra, 'auto_increment' ) !== false;
   }

   /**
    * @return boolean
    */
   public function isPrimaryKey()
   {
      return $this->m_key === 'PRI';
   }

   /**
    * @return boolean
    */
   public function isUnique()
   {
      return $this->m_key === 'UNI';
   }

   /**
    * @return string
    */
   public static function afixedName( string $column_name, string $prefix = '', bool $as_array = false )
   {
      return ($prefix? $prefix . '_' : '') . $column_name . ($as_array? '[]' : '');
   }

   /**
    * @return string
    */
   public function getAfixedName( bool $as_array = false )
   {
      return static::afixedName( $this->getName(), $this->m_tablename, $as_array );
   }

   /**
    * provide column specific form\validator configuration
    * @return Form\Validator\Profile
    */
   public function getValidatorConfig(bool $as_array = false)
   {
      $result = new Form\Validator\Profile();

      $name = $this->getAfixedName();
      $arr  = $as_array? '[]' : '';

      /* special case for array validation: auto_increment columns always "optional",
       * to allow addition of new rows
       */
      if( $as_array && $this->isAutoIncrement() ) $result->optional[] = $name.$arr;
      /* normal case: decide whether optional or required: */
      else if( $this->isRequired() ) $result->required[] = $name.$arr;
      else $result->optional[] = $name.$arr;

      /* defaults */
      $default = $this->getDefault();
      if( isset( $default ) )
      {
         $result->defaults[$name] = $default;
      }

      /* constraints */
      if( isset($this->m_custom_constraint) )
      {
         $result->constraints[$name] = $this->m_custom_constraint;
      }
      else
      {
         $constraint = $this->m_type->getConstraint();
         if( isset($constraint) )
         {
            $result->constraints[$name] = $this->m_type->getConstraint();
         }
      }

      return $result;
   }

   /**
    * {@inheritDoc}
    * @see \dbfe\ColumnIf::doSkip()
    */
   public function doSkip(bool $status = null)
   {
      if( isset($status) ) $this->skip = $status;
      return $this->skip;
   }

   public function makeRequired()
   {
      $this->m_required = true;
   }
}

/**
 * a db table column which allows a application defined set of valid values
 */
class SelectionColumn extends PlainColumn
{
   /** @var array */
   protected $selection = [];

   public function __construct($structure, string $table, array $selection )
   {
      parent::__construct( $structure, $table, false );
      $this->selection = $selection;
   }

   /**
    * get a form specification that can be used as input to Form\Printer
    * @return Form\Printer\Configuration
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false )
   {
      /* first get the list of already stored values */
      $selection = array_filter( $data[$this->getAfixedName()]??[] );
      $selection = array_combine( $selection, $selection );

      /* overwrite it by the given list of allowed values */
      $selection = array_replace( $selection, $this->selection );

      /* add N/A value if valid */
      if( !$this->isRequired() )
      {
         $selection = array_replace( ['' => 'N/A'], $selection );
      }

      return new Form\Printer\Configuration(
         [ 'name'  => $this->getAfixedName($as_array),
           'label' => $lblHdl->get( $this->getName(), $this->m_tablename ),
           'type'  => 'select', 'selection' => $selection ] );
   }
}

/**
 * a db table column which references another table via foreign key constraint
 */
class ReferenceColumn extends PlainColumn implements ReferenceColumnIf
{
   /** @var Table */
   protected $refTable = null;
   /** @var mixed */
   protected $query    = null;
   /** @var mixed */
   protected $disabled = null;

   public function __construct($structure, string $table, Table $ref)
   {
      parent::__construct( $structure, $table, false );
      $this->refTable = $ref;
   }
   
   /**
    * {@inheritDoc}
    * @see \dbfe\ReferenceColumnIf::getTable()
    */
   public function getTable()
   {
      return $this->refTable;
   }

   /**
    * @return array
    */
   public function getReferenceData( array $filter = [], bool $addNA = true )
   {
      $query = $this->query;
      if( !isset($query) )
      {
         // get the content of the other table, select id and name col
         $query = new SelectQuery();
         $query->columns    = [ $this->refTable->getIdColumn()->getName(), $this->refTable->getNameColumn()->getName() ];
         $query->filter     = $filter;
      }

      if( $query instanceof SelectQuery )
      {
         $refData = $this->refTable->query( $query );

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
      else if( is_array($query) )
      {
         // data is given directly already
         return $query;
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
      $query = $this->disabled;
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
         return [];
      }
   }

   /**
    * get a form specification that can be used as input to Form\Printer
    * A "Reference Column" is implemented by providing a select field which
    * allows to select an entry of the reference column.
    * The selectable values are taken from the "name column" of the other table.
    *
    * @return Form\Printer\Configuration
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false )
   {
      return new Form\Printer\Configuration(
         [ 'name'  => $this->getAfixedName($as_array)
         , 'label' => $lblHdl->get( $this->getName(), $this->m_tablename )
         , 'required' => ($this->isRequired() && !$as_array)
         , 'type'  => 'select', 'selection' => $this->getReferenceData(), 'disabled_keys' => $this->getDisabledKeys() ] );
   }

   /**
    * set a customized array to retrieve the selection data set
    * to set the reference content
    * @param SelectQuery|array $query
    * @param SelectQuery|array $disable_keys - any keys that shall no longer be selectable (unless they are already used for a specific field)
    */
   public function setReferenceQuery( $query, $disable_keys = null )
   {
      $this->query    = $query;
      $this->disabled = $disable_keys; 
   }
}

abstract class DispType
{
   const none = 0;
   const link = 1;
   const img  = 2;
}

class FileHandlerColumn extends PlainColumn implements FileHandlerColumnIf
{
   /** var FileHandler */
   protected $fh;
   /** var bool */
   protected $support_delete;
   /** var int */
   protected $display_type;

   /**
    * @param array|PlainColumn $structure
    * @param FileHandlerIf       $fh
    */
   public function __construct($structure, string $table, FileHandlerIf $fh, int $display_type = DispType::link, bool $support_delete = true)
   {
      parent::__construct($structure, $table, false);

      $this->fh             = $fh;
      $this->support_delete = $support_delete;
      $this->display_type   = $display_type;
   }

   /**
    * perform the upload of a file
    * @param array $data
    * @param string $rowid
    */
   public function handleUpload( array &$data, $rowid)
   {
      $colname = $this->getAfixedName();

      if( $this->support_delete && !empty($data[$colname]) )
      {
         $del_cname = $this->getDeleteName();

         if( is_array($data[$del_cname]) )
         {
            $this->dropFiles( $data[$del_cname] );
            $data[$colname] = array_diff( $data[$colname], $data[$del_cname] );
         }
         else if( $data[$del_cname] === $data[$colname] )
         {
            $this->dropFiles( [ $data[$del_cname] ] );
            $data[$colname] = null;
         }
      }

      if( is_array($data[$colname]) && $this->isPrimaryKey() )
      {
         /* only adding of new files allowed in this case */
         $data[$colname] = array_merge($data[$colname], $this->fh->upload( $colname, [], [] ) );
      }
      else
      {
         $data[$colname] = $this->fh->upload( $colname, $data[$colname], $rowid );
      }
   }

   public function dropFiles( array $files )
   {
      foreach( $files as $del )
      {
         $this->fh->delete( $del );
      }
   }

   /**
    * get a form specification that can be used as input to Form\Printer
    *
    * @return Form\Printer\ConfigurationList
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false )
   {
      $dname     = $this->getAfixedName();
      $form_name = $this->getAfixedName($as_array);

      $result = new Form\Printer\ConfigurationList( 
         [ array_merge( [ 'type' => 'file', 'accept' => $this->fh->getAccept()
                        , 'name' => $form_name, 'label' => $lblHdl->get( $this->getName(), $this->m_tablename ) ]
                        , $this->m_formProp ) ] );

      if( isset($data[$dname]) )
      {
         $links = array_map( function($id)
         {
            return [ 'url'  => $this->fh->getFileUrl($id),
                     'name' => $this->fh->getFileName($id) ];
         }, is_array($data[$dname])? $data[$dname] : [$data[$dname]] );

         if( $this->display_type == DispType::link )
         {
            $text = array_map( function($link)
            {
               return sprintf( '<a href="%s" title="%s">%s</a>', $link['url'], $link['name'], $link['name']);
            }, $links);

            $result->add( [ 'type' => 'label', 'tag' => 'p', 'name' => 'link_'.$form_name, 'class' => 'Label',
                            'text' => ($as_array?$text:$text[0]) ], 0 );
         }
         else if( $this->display_type == DispType::img )
         {
            $disp = array_map( function($link)
            {
               return [ new HtmlElement( 'img', [ 'src' => $link['url'], 'alt' => $link['name'] ] ) ];
            }, $links );

            $result[0]['display'] = $as_array? $disp : $disp[0];
         }
         else
         {
            /* no displaying requested */
         }
      }

      if( $this->support_delete && !empty($data[$dname][0]) )
      {
         $result[] = [ 'type' => 'checkbox', 'label' => 'delete', 'name' => $this->getDeleteName()
                     , 'value' => $as_array? $data[$dname] : $data[$dname][0] ];
      }

      return $result;
   }

   /**
    * {@inheritDoc}
    * @see \dbfe\PlainColumn::getValidatorConfig()
    */
   public function getValidatorConfig(bool $as_array = false)
   {
      $result = parent::getValidatorConfig( $as_array );

      if( isset($result) && $this->support_delete )
      {
         $name = $this->getAfixedName() . '_del';
         $result->optional[] = $name;
         $result->constraints[$name] = fvc\Integer(0);
      }

      return $result;
   }

   protected function getDeleteName()
   {
      return $this->getAfixedName() . '_del';
   }
}

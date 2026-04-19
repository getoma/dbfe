<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Printer\Configuration\Configuration as fpc;
use getoma\dbfe\Form\Validator\Constraint\Constraint;
use getoma\dbfe\Table\ColumnTypes\Type;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;

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

   /*  */
   protected array $m_formProp = [];

   /** @var string */
   protected $m_tablename = null;

   /** @var bool */
   protected $skip = false;

   /** @var string */
   protected $m_column_spec = "%s";

   /** @var  */
   protected $m_custom_constraint = null;

   /** @var bool */
   protected $m_required = false;

   /** @var bool */
   protected $m_fixed = false;

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
   public function setCustomConstraint( Constraint $constraint )
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
      return new fpc(
         array_merge( [ 'name'     => $this->getAfixedName($as_array),
                        'label'    => $lblHdl->get( $this->getName(), $this->m_tablename ),
                        'required' => $this->isRequired() && !$as_array,
                        'fixed'    => $this->isFixed()
                      ],
                        $this->m_formProp,
                        $this->m_type->getFormAttributes( $lblHdl, $this->m_tablename . '.' . $this->getName() ),
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
      return $this->m_type->getDefault($this->m_default);
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
    * @return boolean
    */
   public function isFixed()
   {
      return $this->m_fixed || $this->isPrimaryKey();
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
      $result = new \getoma\dbfe\Form\Validator\Profile();

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

   /**
    * {@inheritDoc}
    * @see \dbfe\ColumnIf::makeRequired()
    */
   public function makeRequired()
   {
      $this->m_required = true;
   }

   public function makeFixed()
   {
      $this->m_fixed = true;
   }
}

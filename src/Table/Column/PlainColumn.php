<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Printer\Configuration\Configuration as fpc;
use getoma\dbfe\Form\Printer\Configuration\ConfigurationIf;
use getoma\dbfe\Form\Printer\Configuration\ConfigurationListIf;
use getoma\dbfe\Form\Validator\Constraint\Constraint;
use getoma\dbfe\Table\ColumnTypes\Type;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;

/**
 * a plain db table column
 */
class PlainColumn implements ColumnIf
{
   protected string  $name;
   protected Type    $type;
   protected string  $key;
   protected string  $extra;
   protected mixed   $default;
   protected array   $formProp = [];
   protected bool    $skip = false;
   protected string  $column_spec = "%s";
   protected ?Constraint $custom_constraint = null;
   protected bool    $required = false;
   protected bool    $fixed = false;

   public function __construct(
      PlainColumn|array $structure,
      protected readonly string $tablename,
      bool $heuristic_types = false
   )
   {
      if( $structure instanceof PlainColumn )
      {
         /* copy constructor */
         $this->name    = $structure->name;
         $this->type    = clone $structure->type;
         $this->extra   = $structure->extra;
         $this->default = $structure->default;
         $this->key     = $structure->key;
      }
      else if( is_array($structure) )
      {
         /* construction from structure definition */
         $this->name    = $structure["Field"];
         $this->type    = Type::create( $structure["Type"], $structure["Null"], $structure["Field"], $heuristic_types );
         $this->extra   = $structure["Extra"];
         $this->default = $structure["Default"] == "NULL" ? null : $structure["Default"];
         $this->key     = $structure["Key"];
      }
      else
      {
         throw new \LogicException('unsupported type for structure in column construction');
      }
   }

   public function addFormProperties(array $prop): void
   {
      $this->formProp += $prop;
   }

   /**
    * add a custom constraint from the application
    */
   public function setCustomConstraint( Constraint $constraint ): void
   {
      $this->custom_constraint = $constraint;
   }

   /**
    * get a form specification that can be used as input to Form\Printer
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false): ConfigurationIf|ConfigurationListIf
   {
      return new fpc(
         array_merge( [ 'name'     => $this->getAfixedName($as_array),
                        'label'    => $lblHdl->get( $this->getName(), $this->tablename ),
                        'required' => $this->isRequired() && !$as_array,
                        'fixed'    => $this->isFixed()
                      ],
                        $this->formProp,
                        $this->type->getFormAttributes( $lblHdl, $this->tablename . '.' . $this->getName() ),
            ) );
   }

   /**
    * column name
    *
    * @return string
    */
   public function getName(): string
   {
      return $this->name;
   }

   /**
    * {@inheritDoc}
    * @see \dbfe\ColumnIf::getType()
    */
   public function getType(): string
   {
      $class = get_class($this->type);
      return ($pos = strrpos($class, '\\'))? substr($class, $pos + 1) : $class;
   }

   /**
    * get or set column specifier to use in "select" query
    */
   public function sqlColumnSpec(?string $spec = null): string
   {
      if( isset($spec) ) $this->column_spec = $spec;
      return sprintf($this->column_spec, $this->getName())." ".$this->getName();
   }

   /**
    * default value of column
    *
    * @return mixed
    */
   public function getDefault(): mixed
   {
      return $this->type->getDefault($this->default);
   }

   /**
    * whether this field needs to be filled with a value when writing to the DB
    * It is required if the column is "not null" AND there is no default value
    */
   public function isRequired(): bool
   {
      return $this->required || (!$this->type->isNullOk() && !isset( $this->default ));
   }

   /**
    */
   public function isAutoIncrement(): bool
   {
      return strpos( $this->extra, 'auto_increment' ) !== false;
   }

   /**
    */
   public function isPrimaryKey(): bool
   {
      return $this->key === 'PRI';
   }

   /**
    */
   public function isUnique(): bool
   {
      return $this->key === 'UNI';
   }

   /**
    */
   public function isFixed(): bool
   {
      return $this->fixed || $this->isPrimaryKey();
   }

   /**
    */
   public static function afixedName( string $column_name, string $prefix = '', bool $as_array = false ): string
   {
      return ($prefix? $prefix . '_' : '') . $column_name . ($as_array? '[]' : '');
   }

   /**
    */
   public function getAfixedName( bool $as_array = false ): string
   {
      return static::afixedName( $this->getName(), $this->tablename, $as_array );
   }

   /**
    * provide column specific form\validator configuration
    */
   public function getValidatorConfig(bool $as_array = false): \getoma\dbfe\Form\Validator\Profile
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
      if( isset($this->custom_constraint) )
      {
         $result->constraints[$name] = $this->custom_constraint;
      }
      else
      {
         $constraint = $this->type->getConstraint();
         if( isset($constraint) )
         {
            $result->constraints[$name] = $constraint;
         }
      }

      return $result;
   }

   /**
    */
   public function doSkip(?bool $status = null): bool
   {
      if( isset($status) ) $this->skip = $status;
      return $this->skip;
   }

   /**
    */
   public function makeRequired(): void
   {
      $this->required = true;
   }

   public function makeFixed(): void
   {
      $this->fixed = true;
   }
}

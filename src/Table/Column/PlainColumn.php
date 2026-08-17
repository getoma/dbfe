<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Generator\Node\NodeInterface;
use getoma\dbfe\Table\ColumnTypes\Type;
use Respect\Validation\Validator as Validator;

/**
 * a plain db table column
 */
class PlainColumn implements ColumnIf
{
   protected string    $name;
   protected Type      $type;
   protected string    $key;
   protected string    $extra;
   protected mixed     $default;
   protected array     $formProp = [];
   protected bool      $skip = false;
   protected string    $column_spec = "%s";
   protected Validator $custom_constraint;
   protected bool      $required = false;
   protected bool      $fixed = false;

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
   public function setCustomConstraint( Validator $constraint ): void
   {
      $this->custom_constraint = $constraint;
   }

   public function getFormGeneratorDefinition(): NodeInterface
   {
      return $this->type->getFormNode(
         name:       $this->getAffixedName(),
         required:   $this->isRequired(),
         fixed:      $this->isFixed(),
         attributes: $this->formProp,
      );
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
      return sprintf($this->column_spec, $this->getName())." AS ".$this->getName();
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
      return $this->required || (!$this->type->isNullOk() && !isset($this->default));
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
   public static function affixedName(string $column_name, string $prefix = ''): string
   {
      return ($prefix? $prefix . '-' : '') . $column_name;
   }

   /**
    */
   public function getAffixedName(): string
   {
      return static::affixedName($this->getName(), $this->tablename);
   }

   /**
    * provide column specific form\validator configuration
    */
   public function getValidatorConfig(bool $as_array = false, bool $optional = false): Validator
   {
      $constraint  = $this->custom_constraint ?? $this->type->getConstraint();
      $is_optional = $optional || (!$this->isRequired() && !$this->isAutoIncrement());

      if( $is_optional )
      {
         $constraint = Validator::optional($constraint);
      }

      if( $as_array )
      {
         $constraint = Validator::arrayType()->each( $constraint );
      }

      // in case of "array" inputs, the key may also be missing entirly if the array has zero length
      return Validator::key( $this->getAffixedName(), $constraint, !($is_optional || $as_array) );
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

<?php namespace dbfe\Form\Validator {

require_once 'dbfe/Util/Exception.php';

/**
 * interface of a Form\Validator\Constraint
 */
interface ConstraintIf
{
   /**
    * validate if a value fullfils this constraint.
    * @param mixed $data
    * @return bool
    */
   function validate(string $value);
   
   /**
    * human-readable name for this constraint
    * @return string
    */
   function name();
}

/**
 * base constraint class: checks input against configured regular expression
 */
class Constraint implements ConstraintIf
{
   /** @var string */
   private $re;
   /** @var string */
   private $name;

   function __construct(string $regexp, string $name = null)
   {
      $this->re = $regexp;
      $this->name = $name;
   }

   public function validate(string $value)
   {
      return (preg_match($this->re, $value) > 0);
   }

   public function name()
   {
      return $this->name ?? (new \ReflectionClass($this))->getShortName();
   }
}

/**
 * check if value is a number (int or float)
 */
class Number extends Constraint
{
   private $min, $max;

   function __construct($min = null, $max = null, string $name = null, $float = true)
   {
      $re = '/^[+-]?\d+' . ($float ? '(?:[.,]\d*)?' : '') . '$/';
      parent::__construct($re, $name);

      $this->min = $min;
      $this->max = $max;
   }

   public function validate(string $value)
   {
      $result = parent::validate($value);
      if( $result )
      {
         $result = (isset($this->min) ? $value >= $this->min : true) && (isset($this->max) ? $value <= $this->max : true);
      }
      return $result;
   }
}

/**
 * check if value is an integer
 */
class Integer extends Number
{
   function __construct($min = null, $max = null, string $name = null)
   {
      parent::__construct($min, $max, $name, false);
   }
}

/**
 * enforce a certain number of characters
 */
class Length implements ConstraintIf
{
   private $min;
   private $max;
   private $name;

   function __construct($min, $max = null, string $name = null)
   {
      if( !isset($max) )
      {
         $max = $min;
         $min = 0;
      }
      $this->min  = $min;
      $this->max  = $max;
      $this->name = $name;
   }

   public function validate(string $value)
   {
      $len = strlen($value);
      return (($len >= $this->min) && (($this->max <= 0) || ($this->max < $len)));
   }
   
   public function name()
   {
      return $this->name ?? (new \ReflectionClass($this))->getShortName();
   }
}

/**
 * check if value looks like a currency value
 */
class Currency extends Constraint
{
   function __construct(string $name = null)
   {
      parent::__construct('^\s*[+-]?\d+(?:[,.]\d\d)\s*$/', $name);
   }
}

/**
 * check if value looks like an email address
 */
class Email extends Constraint
{
   function __construct(string $name = null)
   {
      parent::__construct("/^\s*[a-z0-9!#$%&'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\s*$/i", $name);
   }
}

/**
 * check if value looks like a phone number
 */
class Telephone extends Constraint
{

   function __construct(string $name = null)
   {
      parent::__construct('/^\s*(?:\+\d\d)? ?\d+ ?[\/-]? ?[\d -]+\s*$/', $name);
   }
}

/**
 * check if value looks like a certain type of date
 */
class Date extends Constraint
{
   private static $Formats = [
      'generic' => '^\s*\d\d(?:\d\d)?[-\/.]\d\d(?:\d\d)?[-\/.]\d\d(?:\d\d)?\s*',
      'german' => '\d\d?\.\d\d?\.\d\d(?:\d\d)?' ];

   function __construct(string $format = 'generic', string $name = null)
   {
      if( !array_key_exists($format, static::$Formats) )
      {
         throw new \BadMethodCallException("unknown date format '$format'");
      }

      parent::__construct('/^\s*' . static::$Formats[$format] . '\s*$/', $name);
   }
}

/**
 * check if value looks like a time (hh:mm:ss) value
 */
class Time extends Constraint
{
   function __construct(string $name = null)
   {
      parent::__construct('/^\s*\d?\d\:\d?\d(?:\:\d?\d)?\s*$/', $name);
   }
}

/**
 * check if a value is one of a pre-configured list
 */
class Set implements ConstraintIf
{
   /** @var array[string] */
   private $set;
   /** @var string */
   private $name;

   function __construct(array $set, string $name = null)
   {
      $this->set  = $set;
      $this->name = $name;
   }

   public function validate(string $value)
   {
      return in_array($value, $this->set);
   }
   
   public function name()
   {
      return $this->name;
   }
}

/**
 * allow to configure a custom callback to implement a constraint check
 */
class Func implements ConstraintIf
{
   /**@var callable */
   private $func;
   /** @var string */
   private $name;

   function __construct(callable $func, string $name)
   {
      $this->func = $func;
      $this->name = $name;
   }

   public function validate($data)
   {
      $func = $this->func;
      return $func($data);
   }
   
   public function name()
   {
      return $this->name;
   }
}

}

/* fast-constructors: */
namespace dbfe\fvc {

  use \dbfe\Form\Validator as fv;

  function RegExp( $re, $name=null )                  { return new fv\Constraint( $re, $name ); }
  function Integer( $min=null, $max=null, $name=null) { return new fv\Integer( $min, $max, $name ); }
  function Number( $min=null, $max=null, $name=null ) { return new fv\Number($min,$max,$name); }
  function Length( $min=null, $max=null, $name=null ) { return new fv\Length( $min, $max, $name ); }
  function Currency($name=null)                       { return new fv\Currency($name); }
  function Email($name=null)                          { return new fv\Email($name); }
  function Telephone($name=null)                      { return new fv\Telephone($name); }
  function Date($format='generic', $name=null)        { return new fv\Date($format, $name); }
  function Time($name=null)                           { return new fv\Time($name); }
  function Set($set, $name=null)                      { return new fv\Set($set, $name); }
  function Func($func, $name)                         { return new fv\Func($func, $name); }
}

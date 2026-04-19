<?php

namespace getoma\dbfe\Form\Validator\Constraint;

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

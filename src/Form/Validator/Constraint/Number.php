<?php

namespace getoma\dbfe\Form\Validator\Constraint;

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

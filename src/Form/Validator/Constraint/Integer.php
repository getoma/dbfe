<?php

namespace getoma\dbfe\Form\Validator\Constraint;

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

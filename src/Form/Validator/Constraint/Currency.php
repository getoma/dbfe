<?php

namespace getoma\dbfe\Form\Validator\Constraint;

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

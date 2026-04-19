<?php

namespace getoma\dbfe\Form\Validator\Constraint;

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

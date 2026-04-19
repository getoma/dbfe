<?php

namespace getoma\dbfe\Form\Validator\Constraint;

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

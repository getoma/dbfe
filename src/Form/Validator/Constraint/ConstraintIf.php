<?php

namespace getoma\dbfe\Form\Validator\Constraint;

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

<?php

namespace getoma\dbfe\Form\Validator\Constraint;

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

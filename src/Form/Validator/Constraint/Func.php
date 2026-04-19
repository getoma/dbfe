<?php

namespace getoma\dbfe\Form\Validator\Constraint;

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

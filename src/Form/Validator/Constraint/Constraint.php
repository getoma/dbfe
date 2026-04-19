<?php

namespace getoma\dbfe\Form\Validator\Constraint;

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

<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Generator\Node\NodeInterface;
use Respect\Validation\Validator as V;

/**
 * Handle db column Time type
 */
class Time extends Type
{
   public function getFormNode(string $name, bool $required = false, bool $fixed = false, array $attributes = []): NodeInterface
   {
      return new \getoma\dbfe\Form\Generator\Field\TimeField(
         $name,
         $required,
         $fixed,
         $attributes,
      );
   }

   public function getConstraint(): V
   {
      return V::time();
   }
}

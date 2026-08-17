<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Generator\Node\NodeInterface;
use getoma\dbfe\Form\Generator\Field\NumberField;
use Respect\Validation\Validator as V;

/**
 * Handle db column date type:
 */
class Year extends Type
{
   const MIN = 1900;
   const MAX = 9999;

   public function getFormNode(string $name, bool $required = false, bool $fixed = false, array $attributes = []): NodeInterface
   {
      return new NumberField(
         $name,
         $required,
         $fixed,
         min:  self::MIN,
         max:  self::MAX,
         step: 1,
         attributes: $attributes,
      );
   }

   public function getConstraint(): V
   {
      return V::intVal()->between( self::MIN, self::MAX );
   }
}

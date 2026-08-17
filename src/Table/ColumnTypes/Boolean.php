<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Generator\Field\BooleanField;
use getoma\dbfe\Form\Generator\Node\NodeInterface;
use Respect\Validation\Validator as V;

/**
 * Handle db column boolean types:
 * tinyint(1)
 */
class Boolean extends Type
{
   public function getFormNode(string $name, bool $required = false, bool $fixed = false, array $attributes = []): NodeInterface
   {
      return new BooleanField(
         $name,
         $attributes['value'] ?? 1,
         $required,
         $fixed,
         $attributes,
      );
   }

   public function getConstraint(): V
   {
      // accept any non-negative number as well.
      // right now, checkboxes will contain their row number as value for array groups
      // (handled in Table::getFormDefinition), to be cleaned up.
      return V::anyOf(V::boolVal(), V::intVal()->not(V::negative()));
   }
}

<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Generator\Node\NodeInterface;
use Respect\Validation\Validator as V;

class Uri extends Text
{
   public function getFormNode(string $name, bool $required = false, bool $fixed = false, array $attributes = []): NodeInterface
   {
      return new \getoma\dbfe\Form\Generator\Field\UrlField(
         $name,
         $required,
         $fixed,
         $this->maxlen,
         $attributes,
      );
   }

   public function getConstraint(): V
   {
      return V::url();
   }
}

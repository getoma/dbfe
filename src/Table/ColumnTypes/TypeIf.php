<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Generator\Node\NodeInterface;
use Respect\Validation\Validator as Validator;

interface TypeIf
{
   /**
    * whether the value can be NULL
    */
   public function isNullOk(): bool;

   /**
    * allow overriding of database default in input processing
    */
   public function getDefault($db_default = ''): mixed;

   /**
    * create a generator node for this column type
    */
   public function getFormNode(string $name, bool $required = false, bool $fixed = false, array $attributes = []): NodeInterface;

   /**
    */
   public function getConstraint(): Validator;
}

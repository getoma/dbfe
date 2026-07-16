<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
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
    */
   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array;

   /**
    */
   public function getConstraint(): Validator;
}

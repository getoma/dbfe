<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use Respect\Validation\Validator as V;

class Binary extends Type
{
   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      return [];
   }

   public function getConstraint(): V
   {
      return V::scalarVal();
   }
}

<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;

class Binary extends Type
{
   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      return [];
   }

   public function getConstraint()
   {
      return [];
   }
}

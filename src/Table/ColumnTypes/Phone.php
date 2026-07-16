<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use Respect\Validation\Validator as V;

class Phone extends Type
{
   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      return [ 'type' => 'tel' ];
   }

   public function getConstraint(): V
   {
      return V::phone();
   }
}

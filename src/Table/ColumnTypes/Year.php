<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use Respect\Validation\Validator as V;

/**
 * Handle db column date type:
 */
class Year extends Type
{
   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      return [ 'type' => 'Number', 'class' => 'InputYear', 'min' => 1900, 'step' => 1, 'max' => 9999 ];
   }

   public function getConstraint(): V
   {
      return V::intVal()->between( 1900, 9999 );
   }
}

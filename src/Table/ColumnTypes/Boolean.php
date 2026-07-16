<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use Respect\Validation\Validator as V;

/**
 * Handle db column boolean types:
 * tinyint(1)
 */
class Boolean extends Type
{

   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      return [ 'type' => 'checkbox', 'value' => '1' ];
   }

   public function getConstraint(): V
   {
      // accept any non-negative number as well.
      // right now, checkboxes will contain their row number as value for array groups
      // (handled in Table::getFormDefinition), to be cleaned up.
      return V::anyOf(V::boolVal(), V::intVal()->not(V::negative()));
   }
}

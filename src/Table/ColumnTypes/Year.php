<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Validator\Constraint\ConstraintIf;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use getoma\dbfe\Form\Validator\Constraint\FastConstructors as fvc;

/**
 * Handle db column date type:
 */
class Year extends Type
{
   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      return [ 'type' => 'Number', 'class' => 'InputYear', 'min' => 1900, 'step' => 1, 'max' => 9999 ];
   }

   public function getConstraint(): ConstraintIf
   {
      return fvc::Integer(1900,9999);
   }
}

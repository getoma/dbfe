<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Validator\Constraint\ConstraintIf;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use getoma\dbfe\Form\Validator\Constraint\FastConstructors as fvc;

/**
 * Handle db column Time type
 */
class Time extends Type
{
   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      return [ 'type' => 'time' ];
   }

   public function getConstraint(): ConstraintIf
   {
      return fvc::Time();
   }
}

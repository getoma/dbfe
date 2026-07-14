<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Validator\Constraint\ConstraintIf;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use getoma\dbfe\Form\Validator\Constraint\FastConstructors as fvc;

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

   public function getConstraint(): ConstraintIf
   {
      return fvc::Integer( 0 );
   }

   /**
    *
    * {@inheritdoc}
    * @see Type::is_null_ok()
    */
   public function isNullOk(): bool
   {
      return false;
   }
}

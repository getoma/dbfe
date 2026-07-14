<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Validator\Constraint\ConstraintIf;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use getoma\dbfe\Form\Validator\Constraint\FastConstructors as fvc;

class Email extends Text
{
   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      $result = [ 'type' => 'email' ];
      if( isset($this->maxlen) ) $result['size'] = $this->maxlen;
      return $result;
   }

   public function getConstraint(): ConstraintIf
   {
      return fvc::Email();
   }
}

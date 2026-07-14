<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Validator\Constraint\ConstraintIf;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;

class Uri extends Text
{
   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      $result = [ 'type' => 'url' ];
      if( isset($this->maxlen) ) $result['size'] = $this->maxlen;
      return $result;
   }

   public function getConstraint(): ?ConstraintIf
   {
      return parent::getConstraint(); // TODO
   }
}

<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use Respect\Validation\Validator as V;

class Email extends Text
{
   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      $result = [ 'type' => 'email' ];
      if( isset($this->maxlen) ) $result['size'] = $this->maxlen;
      return $result;
   }

   public function getConstraint(): V
   {
      return V::email();
   }
}

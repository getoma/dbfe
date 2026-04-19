<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;

interface TypeIf
{
   /**
    * whether the value can be NULL
    * @return bool
    */
   public function isNullOk();

   /**
    * allow overriding of database default in input processing
    * @return mixed
    */
   public function getDefault($db_default = '');

   /**
    * @return array
    */
   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array;

   /**
    * @return Form\Validator\Constraint
    */
   public function getConstraint();
}

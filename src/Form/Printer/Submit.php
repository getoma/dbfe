<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * INPUT TYPE="SUBMIT/RESET"
 *****************************************************************************************************/
 /* include buttons directly (without <p><label/></p> environment) */
class Submit extends Atomic
{
   protected function getValue($key = 'values')
   {
      $result = null;
      /* the 'values' shall be set in the form directly */
      if( $key != 'values' )
      {
         $result = parent::getValue($key);
      }
      return $result;
   }
}

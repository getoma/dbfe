<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * INPUT TYPE="HIDDEN"
 *****************************************************************************************************/
/* Has to be defined as own class, because the hidden elements shall not be
 * encapsulated within the <p><label /><input /></p> construct like all other atomic elements
 * Now the Container constructor directly uses this class when he finds a type="hidden" child
 */
class Hidden extends Atomic
{
   function __construct($data)
   {
      $data['type'] = 'hidden'; // force/preset type
      parent::__construct($data);
   }
}

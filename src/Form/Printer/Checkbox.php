<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * INPUT TYPE="Checkbox"
 *****************************************************************************************************/
class Checkbox extends Element
{
  function __construct($data)
   {
      /* value has to be handled differently: if 'value' is in values,
       * then we have to be checked
       */
      $name = (substr($data['name'], - 2) === '[]') ? substr($data['name'], 0, - 2) : $data['name'];

      if( isset($data['value']) && isset($data['values'][$name]) &&
         (is_array($data['values'][$name]) ? in_array($data['value'], $data['values'][$name]) : $data['value'] === $data['values'][$name]) )
      {
         $data['checked'] = 'checked';
      }

      $data['values'] = [];

      parent::__construct($data);
   }
}

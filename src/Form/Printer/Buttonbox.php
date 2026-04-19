<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * BUTTONS
 *****************************************************************************************************/
class Buttonbox extends Base
{
  protected function getInfo()
   {
      return [ 'tag' => 'div', 'prefix' => 'Box' ];
   }

   function __construct($data)
   {
      $buttons = $data['buttons'];
      unset($data['buttons']);

      parent::__construct($data, []);

      foreach( $buttons as $type => $value )
      {
         if( is_string($value) )
         {
            $this->push(new Atomic([ 'type' => $type, 'value' => $value]));
         }
         else if( is_array($value) )
         {
            foreach( $value as $name => $caption )
            {
               $this->push( new Atomic([ 'type' => $type, 'name' => $name, 'value' => $caption]) );
            }
         }
         else
         {
            throw new PrinterException('invalid value in ButtonBox');
         }
      }

      $this->skip_ws = true;
   }
}

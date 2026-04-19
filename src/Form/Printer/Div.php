<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * div container
 *****************************************************************************************************/
class Div extends Container
{
   protected function getInfo()
   {
      return ['tag' => 'div', 'prefix' => 'div'];
   }

   function __construct( $data, $htmldef )
   {
      parent::__construct($data, $htmldef);
   }

   public function asHtml($indent = 0, $shift = 2)
   {
      $result = '';
      if( $this->attr['transparent']??false )
      {
         foreach( $this->content as $entry )
         {
            $result .= $entry->asHtml($indent, $shift);
         }
      }
      else
      {
         $result = parent::asHtml($indent, $shift);
      }
      return $result;
   }
}

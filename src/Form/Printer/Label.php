<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * LABEL
 *****************************************************************************************************/
/* type provided to just print out some text
 */
class Label extends Atomic
{
  protected function getInfo()
   {
      return [ 'tag' => $this->params['tag'] ?? 'p', 'prefix' => 'Lbl' ];
   }

   function __construct($data)
   {
      parent::__construct($data);
      if( isset($data['label']) )
      {
         $this->unshift( new Atomic([ 'content' => $data['label'] . ': ', 'tag' => 'span', 'class' => 'label'] ) );
      }
      if( isset($this->params['text']) )
      {
         $this->push($this->params['text']);
      }
   }

   static function _extendStatic()
   {
      static::$paramlist[] = 'text';
   }
}
Label::_extendStatic();

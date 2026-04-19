<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * FIELDSET
 *****************************************************************************************************/
class Fieldset extends Container
{

   protected function getInfo()
   {
      return [ 'tag' => 'fieldset', 'prefix' => 'Fs'];
   }

   function __construct($data)
   {
      /* create the object */
      parent::__construct($data, []);
      /* create legend */
      $this->unshift( new Atomic([ 'tag' => 'legend', 'content' => $this->params['label'] ] ) );
      /* register this fieldset */
      $this->params['fscollect']->add( $this->params['label'], $this->getId() );
   }
}

<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * GENERIC FORM ELEMENT
 *****************************************************************************************************/
class Element extends Base
{
  protected static $inherit = [ 'values', 'invalid', 'errmsg', 'idmanager', 'fscollect', 'type', 'name', 'fixed' ];

   protected function getInfo()
   {
      return [ 'tag' => $this->params['tag'] ?? 'p', 'prefix' => 'Box' ];
   }

   function __construct( $data )
   {
      /* check if field contains a "selection" */
      $selection = [];
      if( isset($data['selection']) )
      {
         $selection = $data['selection'];
         unset($data['selection']);
      }

      /* create this element */
      parent::__construct($data, []);

      /* prepare parameters of input element */
      $class = '\\' . __NAMESPACE__ . '\\Atomic';
      $params = [];
      foreach( static::$inherit as $key )
      {
         if( isset($this->params[$key]) )
         {
            $params[$key] = $this->params[$key];
         }
      }

      /* copy the content */
      if( count($selection) > 0 )
      {
         $params['selection'] = $selection;
         /* this element has to be an atomic container */
         $class = '\\' . __NAMESPACE__ . '\\AtomicContainer';
      }
      /* create defined subelements */
      $input = new $class( array_merge($this->attr, $params) );
      $label = new Atomic( [ 'tag' => 'label', 'name' => $this->params['name'], 'for' => $input->getId(), 'content' => $this->params['label'] ] );
      /* reset the html params of this container element */
      $this->attr = [ 'id' => $this->getId(), 'class' => $this->params['type'] . (isset($this->attr['class']) ? ' ' . $this->attr['class'] : '') ];

      /* add new sub elements to Html\Element content */
      $this->push($label);
      /* add error message if invalid field */
      if( $this->getValue('invalid') )
      {
         $this->push( new Atomic( [ 'tag' => 'span', 'class' => 'error', 'content' => $this->getValue('errmsg') ] ) );
      }
      $this->push($input);

      /*
       * if this element is disabled, add its value
       * additionally as hidden element to preserve it
       * (browsers won't send values of disabled fields)
       */
      /**@var $input Element */
      if( isset($input->attr['disabled']) )
      {
         $hidden = new Hidden( [ 'name' => $params['name'], 'values' => $params['values']] );
         $this->push($hidden);
      }
   }

   /**
    *
    * {@inheritdoc}
    * @see \dbfe\HtmlElement::asHtml()
    */
   public function asHtml($indent = 0, $shift = 2)
   {
      if( !empty($this->content) )
      {
         return parent::asHtml($indent, $shift);
      }
   }
}
<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * ATOMIC CONTAINER (e.g. select)
 *****************************************************************************************************/
class AtomicContainer extends Atomic
{
  function __construct( $data )
   {
      /* first retrieve the defined content */
      $content  = $data['selection'] ?? [];
      unset($data['selection']);

      /* retrieve any optional "disabled options" list */
      $disabled = $data['disabled_keys'] ?? [];
      unset($data['disabled_keys']);

      /* the type has to be the tag itself here */
      $data['tag'] = $data['type'];
      /* create the element */
      parent::__construct($data);
      /* get the currently set value */
      $selected = $this->getValue();
      /* create the options */
      foreach( $content as $key => $value )
      {
         $options = [ 'value' => $key, 'content' => [ $value??'' ], 'tag' => 'option' ];
         if( strval($key) === strval($selected) )
         {
            $options['selected'] = true;
         }

         if( !(@$this->attr['disabled'] || in_array($key, $disabled)) || @$options['selected'] )
         {
            $this->push(new Atomic($options));
         }
      }
   }

   public static function _extendStatic()
   {
      static::$prefixList['select']  = 'Sel';
      static::$htmlDef['select']     = [ 'size' => '1' ];
      static::$htmlparList['select'] = [ 'name' ];
   }
}

AtomicContainer::_extendStatic();

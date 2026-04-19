<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * ATOMIC ELEMENT
 *****************************************************************************************************/
class Atomic extends Base
{
  protected static $prefixList = [
      'text'     => 'Input',
      'checkbox' => 'Check',
      'submit'   => 'Button',
      'reset'    => 'Button',
      'textarea' => 'Input',
      'file'     => 'File'   ];

   protected static $taglist = [ 'textarea' ];

   protected static $htmlparList = [
      'input'    => [ 'type', 'name'],
      'textarea' => ['name']
   ];

   protected static $htmlDef = [
      'input'    => [ 'value' => ''],
      'hidden'   => [ 'value' => ''],
      'password' => [],
      'textarea' => [ 'cols' => '30', 'rows' => '5' ],
      'file'     => []
   ];

   protected static $textContainer = [ 'textarea', 'p', 'td', 'th' ];

   function __construct( $data )
   {
      if( !($data instanceof Configuration\ConfigurationIf) ) $data = new Configuration\Configuration($data);

      /* preset the tag with default */
      if( !isset($data['tag']) )
      {
         $data['tag'] = isset($data['type']) && in_array($data['type'], static::$taglist) ? $data['type'] : 'input';
      }

      /* get list of default html params */
      $htmlDefs =  isset($data['type']) && isset(static::$htmlDef[$data['type']]) ? static::$htmlDef[$data['type']]
                :( isset($data['tag']) && isset(static::$htmlDef[$data['tag']])   ? static::$htmlDef[$data['tag']]
                :                                                                   [] );

      /* create the object */
      parent::__construct($data, $htmlDefs, $data->children()->content());

      /* copy needed params to html attr */
      if( isset(static::$htmlparList[$this->tag]) )
      {
         foreach( static::$htmlparList[$this->tag] as $key )
         {
            if( isset($this->params[$key]) )
            {
               $this->attr[$key] = $this->params[$key];
            }
         }
      }

      /* get the value of this field */
      $value = $this->getValue();

      /* set the value to the output object if there is one */
      if( $value !== null )
      {
         if( in_array($this->tag, static::$textContainer) )
         {
            $this->push($value);
         }
         else if( array_key_exists('value', $this->attr) )
         {
            $this->attr['value'] = $value;
         }

         /* make the object fixed if needed */
         if( @$data['fixed'] && $value )
         {
            $this->attr['disabled'] = true;
            $this->attr['class'] = ((@$this->attr['class']) ? $this->attr['class'] . " " : "") . "fixed";
         }
      }
      else
      {
         /* always add any content to text container elements */
         if( in_array($this->tag, static::$textContainer) )
         {
            $this->push('');
         }
      }
   }

   protected function getInfo()
   {
      if( isset($this->params['prefix']) )
      {
         $prefix = $this->params['prefix'];
      }
      else if( isset($this->params['type']) && array_key_exists($this->params['type'], static::$prefixList) )
      {
         $prefix = static::$prefixList[$this->params['type']];
      }
      else
      {
         $prefix = null;
      }
      return [ 'tag' => $this->params['tag'], 'prefix' => $prefix ];
   }

   static public function _extendStatic()
   {
      static::$paramlist[] = 'tag';
      static::$paramlist[] = 'prefix';
      static::$paramlist[] = 'fixed';
   }
}

Atomic::_extendStatic();

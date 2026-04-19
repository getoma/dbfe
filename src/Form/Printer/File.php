<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * INPUT TYPE="FILE"
 *****************************************************************************************************/
class File extends Element
{
   function __construct($data)
   {
      $values = [];
      $array = false;

      /* save back value setting. Its handled differently for File */
      if( isset($data['values']) )
      {
         $values = $data['values'];
         unset($data['values']);
      }

      $data['type'] = 'file'; // force/preset type

      parent::__construct($data);

      /* get and preevaluate the name */
      $name = $this->params['name'];
      if( substr($name, - 2) === '[]' )
      {
         $name = substr($name, 0, - 2);
         $array = true;
      }

      if( isset($values[$name]) && is_array($values[$name]) )
      {
         $values[$name] = $values[$name][0];
      }

      if( @$this->params['fixed'] && $values[$name] )
      {
         /* still print current value as hidden field */
         $this->content = [ new Hidden([ 'name' => $name . ($array ? '[]' : ''), 'value' => $values[$name]]) ];
      }
      else if( isset($values[$name]) )
      {
         /* output the file path as hidden element, to have everything needed in the post data */
         $this->push(new Hidden( [ 'name' => $name . ($array ? '[]' : ''), 'value' => $values[$name] ] ) );
      }

      foreach( $this->params['display'] ?? [] as $sub )
      {
         $this->push($sub);
      }
   }

   public static function _extendStatic()
   {
      static::$paramlist[] = 'display';
   }
}

File::_extendStatic();

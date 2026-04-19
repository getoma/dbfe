<?php

namespace getoma\dbfe\Form\Printer;


/******************************************************************************************************
 * Array group
 *****************************************************************************************************/
class Arraygroup extends Base
{
   /* list of inherited parameters */
   protected static $inherit = [ 'values', 'invalid', 'errmsg', 'idmanager', 'fscollect' ];

   /* list of element-specific fields which may have to be split */
   protected static $split   = [ 'value', 'text' ];

   /**
    * @param \dbfe\Form\Printer\Configuration $cfg
    * @throws PrinterException
    */
   function __construct( Configuration\ConfigurationIf $cfg )
   {
      $options = [ 'addempty' => true ];
      /** @var mixed $option */
      foreach( $options as $key => &$option )
      {
         if( isset($cfg[$key]) )
         {
            $option = $cfg[$key];
            unset($cfg[$key]);
         }
      }

      /* detach configuration content and rebuild it while processing it */
      $children_list = $cfg->detach();

      /* preset needed parameters (to avoid "array key does not exist" warnings) */
      if( !isset($cfg['name']) ) $cfg['name'] = null;

      /* call parent constructor */
      parent::__construct($cfg, []);

      /* check the lengths of the value arrays to be equal
       *
       * recursively go through all sub elements to also check input elements
       * in deeper nesting
       */
      $names  = [];
      $length = 0;
      /** @var $check_names callable */
      $check_names = function (&$content) use (&$names, &$length, &$cfg, &$check_names) {
         foreach( $content as &$sub )
         {
            if( isset($sub['name']) )
            {
               $name = $sub['name'];
               if( substr($name, - 2) === '[]' ) $name = substr($name, 0, - 2); // remove [] at end of name
               $names[$sub['name']] = $name; // store it back for later

               if( $sub['type'] === 'checkbox' ) continue; /* no length alignment for checkboxes */

               /* check if this array has more entries */
               if( array_key_exists($name, $cfg['values']) && is_array($cfg['values'][$name]) && ($length < count($cfg['values'][$name])) )
               {
                  $length = count($cfg['values'][$name]);
               }
            }
            if( isset($sub['content']) )
            {
               $check_names($sub['content']);
            }
         }
      };
      $check_names($children_list->content());

      /* fill shorter parts up */
      foreach( $children_list->content() as &$sub )
      {
         if( $sub['type'] === 'checkbox' ) continue; /* no length alignment for checkboxes */
         if( empty($sub['name']) ) continue;

         $name = $names[$sub['name']];

         if( isset($cfg['values'][$name]) && is_array($cfg['values'][$name]) && (count($cfg['values'][$name]) < $length) )
         {
            $cfg['values'][$name] = array_pad($cfg['values'][$name], $length, '');
         }
      }

      /* prepare single element list */
      foreach( $children_list->content() as &$sub )
      {
         /* retrieve 'split' parameters */
         $splitdata = [];
         foreach( static::$split as $key )
         {
            if( isset($sub[$key]) )
            {
               $splitdata[$key] = $sub[$key];
               unset($sub[$key]);
            }
         }
         /* check if type is specified for subitem */
         if( !isset($sub['type']) )
         {
            throw new PrinterException('type is missing in element description');
         }
         /* get corresponding class name */
         $class = '\\' . __NAMESPACE__ . '\\' . ucfirst(strtolower($sub['type']));
         /* check if this class exists */
         if( !class_exists($class) )
         {
            $class = '\\' . __NAMESPACE__ . '\\' . 'Element';
         }
         $sub = [ 'data' => $sub, 'class' => $class, 'split' => $splitdata ];
      }

      /* create list of sub elements + one additional, empty entry if enabled */
      for( $idx = 0; $idx < ($length + ($options['addempty'] ? 1 : 0)); $idx ++ )
      {
         $row = [];
         foreach( $children_list->content() as &$sub )
         {
            /* split existing properties */
            foreach( static::$split as $key )
            {
               if( isset($sub['split'][$key]) )
               {
                  if( isset($sub['split'][$key]) && $idx < count($sub['split'][$key]) )
                  {
                     $sub['data'][$key] = $sub['split'][$key][$idx];
                  }
                  else
                  {
                     continue 2; // no value assigned anymore, do not create element
                  }
               }
            }

            /* inherit defined params if they do not already exist in subelement */
            foreach( static::$inherit as $key )
            {
               if( !is_array($cfg[$key]) )      /* this parameter is a scalar, and not split regarding the fields, just copy it */
               {
                  $sub['data'][$key] = $cfg[$key];
               }
               else
               {
                  /* split all data values (regardless whether they are actually used in this element to keep it simple) */
                  foreach( $names as $name )
                  {
                     if( !isset($cfg[$key][$name]) ) /* this field does not exists for this parameter (values, errmsg, etc.) */
                     {
                        $sub['data'][$key][$name] = null;
                     }
                     else if( !is_array($cfg[$key][$name])        /* this parameter is a scalar for this field */
                            ||($sub['data']['type'] === 'checkbox')  /* special handling for checkboxes */
                     )
                     {
                        $sub['data'][$key][$name] = $cfg[$key][$name];
                     }
                     else if( $idx >= count($cfg[$key][$name]) ) /* this is the additional empty field */
                     {
                        $sub['data'][$key][$name] = '';
                     }
                     else /* this parameter is an array for this field */
                     {
                        $sub['data'][$key][$name] = $cfg[$key][$name][$idx];
                     }
                  }
               }
            }

            /* create and store object of subelement class */
            $row[] = new $sub['class'](clone $sub['data'], []);
         }
         /* store this row as <li> element in the list */
         $this->push( new Atomic([ 'tag' => 'li', 'name' => $cfg['name'].'[]', 'prefix' => 'Entry'
                                 , 'idmanager' => $cfg['idmanager'], 'fscollect' => $cfg['fscollect']
                                 , 'content' => $row ]) );
      }
   }

   protected function getInfo()
   {
      return [ 'tag' => 'ul', 'prefix' => 'Group' ];
   }
}


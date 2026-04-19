<?php

namespace getoma\dbfe\Form\Printer;

use getoma\dbfe\Util\HtmlElement\HtmlElementIf;

/******************************************************************************************************
 * BASE CONTAINER CLASS
 *****************************************************************************************************/
class Container extends Base
{
   protected static $inherit = array( 'values', 'invalid', 'errmsg', 'idmanager', 'fscollect');

   protected function getInfo()
   {
      return [ 'tag' => $this->params['tag'], 'prefix' => $this->params['tag'] ];
   }

   /**
    * @param \dbfe\Form\Printer\ConfigurationIf $config
    * @param array $htmldef
    * @throws PrinterException
    */
   function __construct(Configuration\Configuration $config, array $htmldef)
   {
      /* detach content for separate processing */
      $children = $config->detach();

      /* call basic constructor */
      parent::__construct($config, $htmldef);

      /* now parse the content definitions if existing */
      foreach( $children as $sub )
      {
         if( $sub instanceof HtmlElementIf )
         {
            $this->push($sub);
         }
         elseif( $sub instanceof Configuration\ConfigurationIf )
         {
            /* check if type is specified for subitem */
            if( !isset($sub['type']) )
            {
               print_r($sub);
               throw new PrinterException('type is missing in element description');
            }
            /* get corresponding class name */
            $class = '\\' . __NAMESPACE__ . '\\' . ucfirst(strtolower($sub['type']));
            /* check if this class exists */
            if( !class_exists($class) )
            {
               $class = '\\' . __NAMESPACE__ . '\\' . 'Element';
            }

            /* inherit defined params if they do not already exist in subelement */
            foreach( static::$inherit as $key )
            {
               $sub[$key] = $this->params[$key];
            }

            /* create and store object of subelement class */
            $this->push( new $class($sub, []) );
         }
         else
         {
            throw new PrinterException('sub element is non-object and non-array. Cannot handle it');
         }
      }
   }
}
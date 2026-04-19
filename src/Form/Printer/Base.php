<?php

namespace getoma\dbfe\Form\Printer;

/******************************************************************************************************
 * BASE CLASS
 *****************************************************************************************************/
abstract class Base extends \getoma\dbfe\Util\HtmlElement\HtmlElement
{
   protected $params           = [];
   protected static $paramlist = ['name', 'type', 'values', 'invalid', 'errmsg', 'label', 'idmanager', 'fscollect'];
   protected static $htmlsort  = ['type', 'name', 'id', 'class', 'value'];

   private        $info       = null;
   private static $neededInfo = ['prefix', 'tag'];

   /* this function shall return all object-specific information defined in $neededInfo */
   abstract protected function getInfo();

   /**
    *
    * @param Printer\Configuration $config
    * @param array $html_attr
    * @throws PrinterException
    */
   function __construct( $config, $html_attr = [] )
   {
      /* parent constructor, preset html attr,
       * ignore content - this item either has no content anyway, or the content is to be handled in derived constructor
       */
      parent::__construct( '', $html_attr, $config->children()->content() );

      /* split the content of the parameter array in known parameters and html attributes: */
      foreach( $config as $key => $value )
      {
         if( in_array($key, static::$paramlist) )
         {
            $this->params[$key] = $value;
         }
         else
         {
            $this->attr[$key] = $value;
         }
      }

      /* get the object specific information */
      $this->info = $this->getInfo();
      /* check if all needed information was provided */
      foreach( self::$neededInfo as $key )
      {
         /* sanity check: values need to be set, but can be null */
         if( !array_key_exists( $key, $this->info ) )
         {
            throw new PrinterException("needed information tag '$key' is missing.\n");
         }
      }

      /* store the tag for html\element */
      $this->tag = $this->info['tag'];

      /* autogeneration of commonly used parameters */
      if( isset($this->params['name']) )
      {
         /* generate htmlparam id from name */
         if( isset($this->params['idmanager']) && isset($this->info['prefix']) && isset($this->params['name']) )
         {
            $this->attr['id'] = $this->params['idmanager']->createId($this->info['prefix'] . $this->params['name']);
         }
         /* set label=name */
         if( ! array_key_exists('label', $this->params) )
         {
            $this->params['label'] = $this->getName();
         }
      }
   }

   protected function getValue($key = 'values')
   {
      $returnvalue = null;
      $name = $this->getName();
      if( array_key_exists($key, $this->params) && array_key_exists($name, $this->params[$key]) )
      {
         $returnvalue = $this->params[$key][$name];
      }
      if( is_array($returnvalue) && (1 == count($returnvalue)) )
      {
         $returnvalue = $returnvalue[0];
      }
      return $returnvalue;
   }

   public function getId()
   {
      return $this->attr['id'];
   }

   public function getName()
   {
      $name = null;
      if( isset($this->params['name']) )
      {
         $name = $this->params['name'];
         if( substr($name, - 2) === '[]' ) $name = substr($name, 0, - 2); // remove array() at end of name
      }
      return $name;
   }
}
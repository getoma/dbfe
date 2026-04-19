<?php

namespace getoma\dbfe\Form\Printer;

use getoma\dbfe\Util\HtmlElement\HtmlElement;

/******************************************************************************************************
 * ROOT CLASS
 *****************************************************************************************************/
class Printer extends Container
{
   protected $htmlDef = [ 'method' => 'post' ];

   private static $FormValidatorKeys = [ 'data' => 'values', 'msg' => 'errmsg', 'is_valid' => 'invalid'];

   protected function getInfo()
   {
      return [ 'tag' => 'form', 'prefix' => 'Form'];
   }

   /**
    * @param Printer\Configuration $cfg
    */
   function __construct( Configuration\Configuration $cfg )
   {
      /* create the ID manager */
      $cfg['idmanager'] = new IdManager();
      $cfg['fscollect'] = new StringCollector();

      /* initialize values/errmsg/valid if not given */
      foreach( self::$FormValidatorKeys as $fvKey => $key )
      {
         /* preset to empty array */
         if( !isset($cfg[$key]) ) $cfg[$key] = [];
         /* copy FormValidator content */
         if( isset($cfg['FormValidator']) )
         {
            $cfg[$key] = array_merge($cfg[$key], $cfg['FormValidator']->$fvKey);
         }
      }
      /* logic of IsValid/invalid has to be inverted ==> all unregistered fields are valid */
      foreach( $cfg['invalid'] as &$val )
      {
         $val = !$val;
      }

      /* remove FormValidator from data, it shall not be further inherited */
      unset($cfg['FormValidator']);

      /* preset the inherited parameters if they do not exist */
      foreach( static::$inherit as $key )
      {
         if( !isset( $cfg[$key] ) )
         {
            $cfg[$key] = null;
         }
      }

      /* construct the element */
      parent::__construct($cfg, $this->htmlDef);

      /* prepend the fieldset navigation menu, if there are at least as many fieldsets as defined via nav_threshold */
      if( !isset($cfg['nav_threshold']) ) // set a default value if not set
      {
         $cfg['nav_threshold'] = 5;
      }

      if( $cfg['nav_threshold'] > 0 ) // 0 -> disabled
      {
         $navcfg = $cfg['fscollect']->getList();

         if( count($navcfg) >= $cfg['nav_threshold'] )
         {
            $navmenu = new HtmlElement('ul', ['class' => 'nav' ] );
            foreach( $navcfg as $id => $caption )
            {
               $navmenu->push( ['li', [], [ ['a', [ 'href' => '#'.$id ], $caption ] ] ] );
            }
            $this->unshift($navmenu);

            /* set up explicit action (->without possible anchor link) */
            $this->attr['action'] = $_SERVER['REQUEST_URI'];
         }
      }
   }
}

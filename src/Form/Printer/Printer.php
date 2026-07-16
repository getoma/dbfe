<?php

namespace getoma\dbfe\Form\Printer;

use getoma\dbfe\Util\HtmlElement\HtmlElement;

/******************************************************************************************************
 * ROOT CLASS
 *****************************************************************************************************/
class Printer extends Container
{
   protected $htmlDef = [ 'method' => 'post' ];

   protected function getInfo()
   {
      return [ 'tag' => 'form', 'prefix' => 'Form'];
   }

   /**
    * @param \getoma\dbfe\Form\Printer\Configuration\Configuration $cfg
    */
   function __construct( \getoma\dbfe\Form\Printer\Configuration\Configuration $cfg )
   {
      /* create the ID manager */
      $cfg['idmanager'] = new IdManager();
      $cfg['fscollect'] = new StringCollector();

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

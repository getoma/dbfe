<?php

namespace getoma\dbfe\Form\Printer;

use getoma\dbfe\Util\HtmlElement\HtmlElement;

/******************************************************************************************************
 * Table
 *****************************************************************************************************/
class Table extends ArrayGroup
{
   /**
    * @param Configuration\Configuration $data
    */
   function __construct( Configuration\Configuration $data )
   {
      /* extract the headers from the content ("label") */
      $headers = array_map( function( $cell )
      {
         return new HtmlElement('th', [], [ $cell['label'] ] );
      }, $data->children()->content() );
      $data['addempty'] = false;

      /* create the actual content as array group */
      parent::__construct($data);

      foreach( $this->content as $row )
      {
         $row->tag = 'tr';
      }
      $this->content = [ new HtmlElement( 'tbody', [], $this->content ) ];
      $this->unshift( new HtmlElement('thead', [], [ [ 'tr', [], $headers ] ]) );
   }

   protected function getInfo()
   {
      return ['tag' => 'table', 'prefix' => 'view'];
   }
}

class Cell extends Atomic
{
   function __construct( $data )
   {
      $data['tag'] = 'td';       // force/preset tag
      parent::__construct( $data );
   }
}


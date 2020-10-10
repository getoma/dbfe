<?php namespace dbfe;

interface HtmlElementIf
{
   function asHtml($indent = 0, $shift = 2);
   
   /**
    * whether this is a "complex" element.
    * Based on this attribute, HtmlElement::as_html() will decide
    * whether to add additional newlines/whitespaces around it to
    * "pretty print" it.
    * possible implementation: return ( count($children)>1 );
    * @return bool 
    */
   function isComplex();
}

class HtmlElement implements HtmlElementIf
{
   public $attr = [];

   public $tag = null;

   public $content = [];

   public $skip_ws = false;

   function __construct(string $tag, array $attr = [], $content = [])
   {
      $this->attr = $attr;
      $this->tag = $tag;
      
      if( !is_array($content) ) $content = [ $content ];

      foreach( $content as $subitem )
      {
         $this->push( $subitem );
      }
   }
   
   public function isComplex()
   {
      return (count($this->content) > 1);
   }

   public function asHtml($indent = 0, $shift = 2)
   {
      $attr = $this->attr;
      $htmlattr = join( ' ', array_map(
                  function ($key) use ($attr)
                  {
                     if( $attr[$key] === true ) return $key;
                     else if( $attr[$key] === false ) return '';
                     else return sprintf( '%s="%s"', $key, $attr[$key] );
                  }, array_keys( $this->attr ) ) );
      $indentstr = str_repeat( ' ', $indent );

      $is_void = in_array( $this->tag, self::VOID_ELEMENTS );

      $result = sprintf( "%s<%s%s>", $indentstr, $this->tag, ($htmlattr? ' '.$htmlattr : '') );

      if( !$is_void )
      {
         if( $this->content )
         {
            if( (count( $this->content ) > 1) || is_object( $this->content[0] ) ) /* wenn mehr als ein Eintrag oder ein weiteres Element */
            {
               if( (count($this->content) === 1) && !$this->content[0]->isComplex() )
               {
                  $result .= $this->content[0]->asHtml(0,$shift);
               }
               else if( $this->skip_ws )
               {
                  foreach( $this->content as $subitem )
                  {
                     $result .= is_object($subitem)? $subitem->asHtml( 0, 0 ) : $subitem;
                  }
               }
               else
               {
                  $result .= "\n";
                  foreach( $this->content as $subitem ) /* allen Inhalt ausgeben */
                  {
                     if( is_object( $subitem ) )
                     {
                        $result .= $subitem->asHtml( $indent + $shift, $shift ) . "\n";
                     }
                     else
                     {
                        $result .= $indentstr . str_repeat( ' ', $shift ) . $subitem . "\n";
                     }
                  }
                  $result .= "$indentstr";
               }
            }
            else
            {
               /*nur Text als inhalt: ohne zusätzliche Zeilenumbrüche ausgeben */
               $result .= $this->content[0];
            }
         }
         $result .= "</" . $this->tag . ">";
      }
      return $result;
   }

   public function push($content)
   {
      if( is_object( $content ) || !is_array( $content ) )
      {
         $this->content[] = $content;
      }
      else
      {
         $class = get_class( $this );

         while( count($content) < 3 ) $content[] = [];

         $this->content[] = new $class( $content[0], $content[1], $content[2] );
      }
   }

   public function unshift($content)
   {
      if( is_object( $content ) || !is_array( $content ) )
      {
         array_unshift( $this->content, $content );
      }
      else
      {
         $class = get_class( $this );

         while( count($content) < 3 ) $content[] = [];

         array_unshift( $this->content, new $class( $content[0], $content[1], $content[2] ) );
      }
   }

   private const VOID_ELEMENTS = [
         'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
         'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr'
   ];
}

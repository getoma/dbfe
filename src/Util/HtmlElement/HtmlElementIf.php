<?php

namespace getoma\dbfe\Util\HtmlElement;

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
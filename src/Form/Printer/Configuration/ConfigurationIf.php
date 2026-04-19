<?php

namespace getoma\dbfe\Form\Printer\Configuration;

interface ConfigurationIf extends \ArrayAccess, \IteratorAggregate
{
   /**
    * return the name of the entry
    * @return string
    */
   function name();

   /**
    * provide access to content
    * @return ConfigurationListIf
    */
   function & children();

   /**
    * detach the children from this element and return them
    * @return ConfigurationListIf
    */
   function detach();
}

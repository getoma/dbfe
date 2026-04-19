<?php

namespace getoma\dbfe\Form\Printer\Configuration;

interface ConfigurationListIf extends \Countable, \ArrayAccess, \IteratorAggregate
{
   /**
    * @param string $name
    * @return ConfigurationIterator
    * find a ConfigurationItem with a specific name in this configuration tree.
    */
   function find( string $name );

   /**
    * @param  mixed $cfg
    * @param  string|ConfigurationIterator  $after
    * @return ConfigurationIterator         iterator to the last added element
    * it $after given, add behind this element.
    * if $after is numerical, add at this position into exactly this container
    * adding works throughout the whole tree (-> element is searched recursively)
    */
   function add( $cfg, $after = null );

   /**
    * @param  string|ConfigurationIterator $name
    * @param  int                          $count
    * @return Configuration|ConfigurationList the removed element(s)
    *
    * if $count given, Configuration object containing all removed elements returned (even if $count = 1).
    * if $count not given, the (exactly one) ConfigurationItem is returned.
    *
    * element specified by $name may be placed anywhere in the configuration tree, a
    * recursive search is performed.
    */
   function remove( $name, ?int $count = null );

   /**
    *
    * @param  string|ConfigurationIterator          $name
    * @param  Configuration|ConfigurationList|array $replacement
    * @param  int                                   $count
    * @return NULL|ConfigurationIterator
    *
    * Replace $count elements, starting from the position of $name, with $replacement
    *
    * $name may be anywhere within the tree, a recursive search is performed.
    */
   function replace( $name, $replacement, int $count = 1 );

   /**
    * direct access to the underlying array of Configuration items
    * @return array
    */
   function & content();

   /**
    * direct access to the first element
    */
   function front();

   /**
    * direct access to the last element
    */
   function back();
}

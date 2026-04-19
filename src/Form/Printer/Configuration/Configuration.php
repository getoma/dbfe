<?php

namespace getoma\dbfe\Form\Printer\Configuration;

use getoma\dbfe\Util\HtmlElement\HtmlElementIf;

/**
 * class to easily manage/modify a configuration for Form\Printer
 */
class Configuration implements ConfigurationIf
{
   /**@var ConfigurationList */
   private $content;

   /**@var array */
   private $attributes;

   function __construct( array $cfg )
   {
      if( isset($cfg[0]) ) throw new \LogicException("attempt to create Configuration with numerical array");
      $this->content = new ConfigurationList($cfg['content']??[]);
      unset($cfg['content']);
      $this->attributes = $cfg;
   }

   /**
    * @return string
    */
   public function name()
   {
      return $this->attributes['name']??null;
   }

   /**
    * provide access to content
    * @return ConfigurationList
    */
   public function & children()
   {
      return $this->content;
   }

   /**
    * {@inheritDoc}
    * @see \dbfe\Form\Printer\ConfigurationIf::detach()
    * @return ConfigurationListIf
    */
   public function detach()
   {
      $result = $this->content;
      $this->content = new ConfigurationList();
      return $result;
   }

   /**
    * {@inheritDoc}
    * @see \ArrayAccess::offsetGet()
    * @return mixed
    * (php8 requires return type mixed for this method, but this would not be supported by php7)
    */
   #[\ReturnTypeWillChange]
   public function & offsetGet($offset)
   {
      return $this->attributes[$offset];
   }

   /**
    * {@inheritDoc}
    * @see \ArrayAccess::offsetExists()
    */
   public function offsetExists($offset): bool
   {
      return isset($this->attributes[$offset]);
   }

   /**
    * {@inheritDoc}
    * @see \ArrayAccess::offsetUnset()
    */
   public function offsetUnset($offset): void
   {
      unset($this->attributes[$offset]);
   }

   /**
    * {@inheritDoc}
    * @see \ArrayAccess::offsetSet()
    */
   public function offsetSet($offset, $value): void
   {
      $this->attributes[$offset] = $value;
   }

   /**
    * {@inheritDoc}
    * @see \IteratorAggregate::getIterator()
    */
   public function getIterator(): \Traversable
   {
      return new \ArrayIterator($this->attributes);
   }

}

/**
 * base class to mock a DummyConfiguration in order to
 * include non-configuration items (e.g. already generated html elements)
 * in a configuration list
 */
abstract class DummyConfiguration implements ConfigurationIf
{

   // (php8 requires return type mixed for this method, but this would not be supported by php7)
   #[\ReturnTypeWillChange]
   public function offsetGet($offset)
   {
      return null;
   }

   public function offsetExists($offset): bool
   {
      return false;
   }

   public function offsetUnset($offset): void
   {
      throw new \LogicException('cannot modify');
   }

   public function offsetSet($offset, $value): void
   {
      throw new \LogicException('cannot modify');
   }

   public function name()
   {
      return null;
   }

   public function & children()
   {
      return new ConfigurationList();
   }

   public function detach()
   {
      return new ConfigurationList();
   }

   public function getIterator(): \Traversable
   {
      return new \ArrayIterator();
   }
}

/**
 * manage pre-generated HtmlElement as configuration item
 */
class HtmlItem extends DummyConfiguration implements HtmlElementIf
{
   private $item;

   function __construct( HtmlElementIf $item)
   {
      $this->item = $item;
   }

   public function asHtml($indent = 0, $shift = 2)
   {
      return $this->item->asHtml($indent, $shift);
   }

   public function isComplex()
   {
      return $this->item->isComplex();
   }

}

/**
 * manage pure text elements as configuration item
 */
class TextItem extends DummyConfiguration implements HtmlElementIf
{
   private $item;

   function __construct( string $item)
   {
      $this->item = $item;
   }

   public function asHtml($indent = 0, $shift = 2)
   {
      return $this->item;
   }

   public function isComplex()
   {
      return false;
   }

}

/***
 * container class to manage a list of Configuration items
 */
class ConfigurationList implements ConfigurationListIf
{
   /** @var Configuration[] */
   private array $content;

   function __construct( $cfg = [] )
   {
      $this->content = self::_validate_list($cfg);
   }

   public function & content()
   {
      return $this->content;
   }

   public function front()
   {
      return $this->content[0];
   }

   public function back()
   {
      return array_slice( $this->content, -1 )[0];
   }

   /**
    * @param string $name
    * @return ConfigurationIterator
    * find a ConfigurationItem with a specific name in this configuration tree
    * depth-first search, as \Form\Printer configurations aren't very deep,
    * but may have a lot of nodes on a single level.
    */
   public function find( string $name )
   {
      $it = [ $this->getIterator() ];
      while( $it[0]->valid() && ($it[0]->current()->name() !== $name) )
      {
         /* this container is not the right one, but
          * check its children (if any)
          */
         if( $it[0]->hasChildren() )
         {
            array_unshift( $it, $it[0]->getChildren() );
         }
         else
         {
            /* if also no children, then continue */
            $it[0]->next();
         }

         if( (count($it) > 1) && !$it[0]->valid() )
         {
            array_shift( $it );
            $it[0]->next();
         }
      }
      return $it[0];
   }

   /**
    * @param  array|Configuration|ConfigurationList    $cfg
    * @param  string|ConfigurationIterator|int         $after
    * @return ConfigurationIterator                    iterator to the last added element
    * if $after given, add behind element this element.
    * if $after is numerical, add at this position into this container
    * adding works throughout the whole tree (-> element is searched recursively)
    */
   public function add( $cfg, $after = null )
   {
      $to_add = self::_validate_list($cfg);

      if( !isset($after) )
      {
         $this->content = array_merge( $this->content, $to_add );
         return new ConfigurationIterator($this, $this->count()-1);
      }
      else if( is_numeric($after) )
      {
         array_splice( $this->content, $after, 0, $to_add );
         return new ConfigurationIterator($this, $after + count($to_add) - 1 );
      }
      else
      {
         $it = $this->_check_and_find($after);
         array_splice( $it->container()->content, $it->key()+1, 0, $to_add );
         $it->seek( $it->key() + count($to_add) - 1 );
         return $it;
      }
   }

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
   public function remove( $name, ?int $count = null )
   {
      $it = $this->_check_and_find($name);
      $cut = array_splice( $it->container()->content, $it->key(), isset($count)?$count:1 );

      if( isset($count) )
      {
         return new ConfigurationList($cut);
      }
      else
      {
         return $cut[0];
      }
   }

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
   public function replace( $name, $replacement, int $count = 1 )
   {
      $it   = $this->_check_and_find($name);
      $repl = self::_validate_list($replacement);

      array_splice( $it->container()->content, $it->key(), $count, $repl );

      $it->seek( $it->key() + count($repl) - 1 );
      return $it;
   }

   /**
    * find an element identified by $key, and throw if it cannot be found
    * To be used by all methods that should only be called with a specific
    * key if it is clear that this key is really existing.
    *
    * @param string|ConfigurationIterator $key
    * @throws \LogicException
    * @return ConfigurationIterator
    */
   private function _check_and_find($key)
   {
      $it = null;
      if( is_string($key) )
      {
         $it = $this->find($key);
         if( !$it->valid() )
         {
            throw new \LogicException("element '$key' not found.");
         }
      }
      else if( $key instanceof ConfigurationIterator )
      {
         $it = $key;
         if( !$it->valid() )
         {
            throw new \LogicException("provided iterator not valid.");
         }
      }
      else
      {
         throw new \LogicException("invalid parameter for 'after'!");
      }
      return $it;
   }

   /**
    * check if provided value is valid for being added to content
    * perform any necessary preprocessing to turn it into ConfigurationIf
    *
    * @param mixed $value
    * @throws \LogicException
    * @return ConfigurationIf
    */
   private static function _validate_value( $value )
   {
      if( is_array($value) )
      {
         if( !isset( $value[0] ) )
         {
            $value = new Configuration($value);
         }
         else
         {
            throw new \LogicException("invalid parameter Form\Printer configuration input!");
         }
      }
      else if( $value instanceof ConfigurationIf )
      {
         /* nothing to do */
      }
      else if( $value instanceof HtmlElementIf )
      {
         $value = new HtmlItem($value);
      }
      else if( is_string($value) )
      {
         $value = new TextItem($value);
      }
      else
      {
         throw new \LogicException("invalid parameter Form\Printer configuration input!");
      }

      return $value;
   }

   private static $count = 0;

   /**
    * validate an input value that may either be a configuration list or single item
    * @param  mixed $list
    * @return array
    */
   private static function _validate_list( $list )
   {
      /* validate/preprocess input */
      if( empty($list) )
      {
         return [];
      }
      else if( $list instanceof ConfigurationIf )
      {
         return [ $list ];
      }
      else if( $list instanceof ConfigurationListIf )
      {
         return $list->content();
      }
      else
      {
         return array_map( self::class.'::_validate_value', (is_array($list) && isset($list[0]))? $list : [ $list ] );
      }
   }

   /**
    * {@inheritDoc}
    * @see \Countable::count()
    * @return int
    */
   public function count(): int
   {
      return count( $this->content );
   }

   /**
    * {@inheritDoc}
    * @see \ArrayAccess::offsetGet()
    * @return mixed
    * (php8 requires return type mixed for this method, but this would not be supported by php7)
    */
   #[\ReturnTypeWillChange]
   public function & offsetGet($offset)
   {
      return $this->content[$offset];
   }

   /**
    * {@inheritDoc}
    * @see \ArrayAccess::offsetExists()
    * @return bool
    */
   public function offsetExists($offset): bool
   {
      return isset($this->content[$offset]);
   }

   /**
    * {@inheritDoc}
    * @see \ArrayAccess::offsetUnset()
    */
   public function offsetUnset($offset): void
   {
      unset($this->content[$offset]);
   }

   /**
    * {@inheritDoc}
    * @see \ArrayAccess::offsetSet()
    */
   public function offsetSet($offset, $value): void
   {
      $value = self::_validate_value($value);
      if( is_null($offset) )
      {
         $this->content[] = $value;
      }
      else if( is_numeric($offset) )
      {
         $this->content[$offset] = $value;
      }
      else
      {
         throw new \LogicException("invalid key for Configuration list");
      }
   }

   /**
    * {@inheritDoc}
    * @see \IteratorAggregate::getIterator()
    * @return ConfigurationIterator
    */
   public function getIterator(): \Traversable
   {
      return new ConfigurationIterator($this);
   }
}

<?php

namespace getoma\dbfe\Form\Printer\Configuration;

class ConfigurationIterator implements \SeekableIterator, \RecursiveIterator
{
   /**@var ConfigurationList */
   private $container;
   /**@var int */
   private $index;

   public function __construct( ConfigurationList $container, int $index = 0 )
   {
      $this->container = $container;
      $this->index     = $index;
   }

   /**
    * @return ConfigurationList
    */
   public function container()
   {
      return $this->container;
   }

   /**
    * {@inheritDoc}
    * @see \SeekableIterator::next()
    */
   public function next(): void
   {
      $this->index += 1;
   }

   /**
    * {@inheritDoc}
    * @see \SeekableIterator::valid()
    */
   public function valid(): bool
   {
      return ($this->index < $this->container->count());
   }

   /**
    * {@inheritDoc}
    * @see \SeekableIterator::current()
    * @return mixed
    * (php8 requires return type mixed for this method, but this would not be supported by php7)
    */
   #[\ReturnTypeWillChange]
   public function current()
   {
      return $this->container->offsetGet($this->index);
   }

   public function rewind(): void
   {
      $this->index = 0;
   }

   /**
    * {@inheritDoc}
    * @see \SeekableIterator::key()
    * @return mixed
    * (php8 requires return type mixed for this method, but this would not be supported by php7)
    */
   #[\ReturnTypeWillChange]
   public function key()
   {
      return $this->valid()? $this->index : null;
   }

   public function seek($position): void
   {
      if( $this->container->offsetExists($position) )
      {
         $this->index = $position;
      }
      else
      {
         throw new \OutOfBoundsException("invalid ConfigurationList position");
      }
   }


   /**
    * {@inheritDoc}
    * @see \RecursiveIterator::getChildren()
    * @return ConfigurationIterator
    */
   public function getChildren(): ConfigurationIterator
   {
      if( $this->valid() )
      {
         return $this->current()->children()->getIterator();
      }
      else
      {
         return new ConfigurationIterator( new ConfigurationList() );
      }
   }

   /**
    * @return bool
    */
   public function hasChildren(): bool
   {
      return $this->valid() && $this->current()->children()->count();
   }
}

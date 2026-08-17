<?php

namespace getoma\dbfe\Form\Generator\Node;

abstract class AbstractNode implements NodeInterface
{
   public function __construct(
      private readonly string $nodeName,
      private readonly array $attributes = [],
      private readonly ?string $label = null,
   )
   {
   }

   /** @var string[] $nodeTypes global dictionary of class --> node type */
   private static array $nodeTypes = [];

   public static function type(): string
   {
      if( !isset(self::$nodeTypes[static::class]) )
      {
         /* derive from class name, e.g.
          * TelField -> tel
          * ArrayGroupNode -> array-group
          */
         // get the class name without namespace
         $className = substr(static::class, strrpos(static::class, '\\') + 1);
         // split into words: (n-1) consecutive upper cases, OR 1 uppercase + x lower cases are one word
         preg_match_all('/[A-Z]+(?=[A-Z]|$)|[A-Z][a-z]+/', $className, $matches);
         // skip the last word, connect all words with '-', change to lower case
         self::$nodeTypes[static::class] = strtolower(implode('-', array_slice($matches[0], 0, -1)));
      }
      return self::$nodeTypes[static::class];
   }

   public function name(): string
   {
      return $this->nodeName;
   }

   /**
    * inferrence helper to convert object into an array
    * for which actual data/errors are not needed, yet.
    */
   public function toArray(): array
   {
      $result = [ 'type' => $this->type(), 'name' => $this->name() ];

      if( $this->label !== null )
      {
         $result['label'] = $this->label;
      }

      if( $this->attributes )
      {
         $result['attributes'] = $this->attributes;
      }

      return $result;
   }
}
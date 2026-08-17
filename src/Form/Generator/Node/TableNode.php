<?php

namespace getoma\dbfe\Form\Generator\Node;

use getoma\dbfe\Form\Generator\Field\CellField;

class TableNode extends ArrayGroupNode
{
   public function __construct(string $name, array $attributes = [], ?string $label = null)
   {
      parent::__construct($name, $attributes, '', false, $label);
   }

   public function add(NodeInterface $child): static
   {
      if( !($child instanceof CellField) )
      {
         throw new \InvalidArgumentException('TableNode only accepts CellField children.');
      }

      return parent::add($child);
   }
}
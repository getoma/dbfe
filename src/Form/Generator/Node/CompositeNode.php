<?php

namespace getoma\dbfe\Form\Generator\Node;

abstract class CompositeNode extends AbstractNode
{
   /** @var NodeInterface[] */
   private array $children = [];

   public function add(NodeInterface $child): static
   {
      $this->children[] = $child;
      return $this;
   }

   /** @return NodeInterface[] */
   public function children(): array
   {
      return $this->children;
   }
}
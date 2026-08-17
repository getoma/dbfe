<?php

namespace getoma\dbfe\Form\Generator\Node;

class GroupNode extends CompositeNode
{
   private NodeInterface $selector;

   public function setSelector(NodeInterface $selector): static
   {
      $this->selector = $selector;
      return $this;
   }

   /** @return NodeInterface */
   public function selector(): NodeInterface
   {
      return $this->selector;
   }

   public function infer(array $values, array $errors = [], string $field_id = ''): array
   {
      $result = $this->toArray();
      $result['children'] = array_map(
         static fn(NodeInterface $child): array => $child->infer($values, $errors, $field_id),
         $this->children()
      );
      if( isset($this->selector) )
      {
         $result['selector'] = $this->selector->infer($values, $errors, $field_id);
      }
      return $result;
   }
}
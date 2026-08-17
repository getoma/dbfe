<?php

namespace getoma\dbfe\Form\Generator\Field;

use getoma\dbfe\Form\Generator\Node\AbstractNode;

class CellField extends AbstractNode
{
   public function infer(array $values, array $errors = [], string $field_id = ''): array
   {
      $result = $this->toArray();
      if( array_key_exists($this->name(), $values) )
      {
         $result['value'] = $values[$this->name()];
      }
      return $result;
   }
}
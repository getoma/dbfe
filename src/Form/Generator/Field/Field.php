<?php

namespace getoma\dbfe\Form\Generator\Field;

use getoma\dbfe\Form\Generator\Node\AbstractNode;

abstract class Field extends AbstractNode
{
   public function __construct(
      string $name,
      bool $required = false,
      bool $fixed = false,
      array $attributes = [],
      ?string $label = null,
   )
   {
      if( $required ) $attributes['required'] = true;
      if( $fixed    ) $attributes['disabled'] = true;
      parent::__construct($name, $attributes, $label);
   }

   public function infer(array $values, array $errors = [], string $field_id = ''): array
   {
      $result = $this->toArray();

      if( array_key_exists($this->name(), $values) )
      {
         $result['value'] = $values[$this->name()];
      }

      if( array_key_exists($this->name(), $errors) )
      {
         $result['error'] = $errors[$this->name()];
      }

      if( $field_id )
      {
         $result['field_id'] = $field_id;
      }

      return $result;
   }
}
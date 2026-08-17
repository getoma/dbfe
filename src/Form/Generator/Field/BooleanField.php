<?php

namespace getoma\dbfe\Form\Generator\Field;

class BooleanField extends Field
{
   public function __construct(
      string $name,
      mixed  $checkedValue = 1,
      bool   $required = false,
      bool   $fixed = false,
      array  $attributes = [],
      ?string $label = null,
   )
   {
      parent::__construct($name, $required, $fixed, [ 'value' => $checkedValue ] + $attributes, $label);
   }

   public function infer(array $values, array $errors = [], string $field_id = ''): array
   {
      $result = parent::infer($values, $errors, $field_id);
      $result['value'] = isset($result['value']) && $result['value'] === $result['attributes']['value'];
      return $result;
   }


}
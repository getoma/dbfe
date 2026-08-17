<?php

namespace getoma\dbfe\Form\Generator\Field;

class NumberField extends Field
{
   public function __construct(
      string $name,
      bool $required = false,
      bool $fixed = false,
      int|float|null $min = null,
      int|float|null $max = null,
      int|float|null $step = null,
      array $attributes = [],
      ?string $label = null,
   )
   {
      foreach( [ 'min' => $min, 'max' => $max, 'step' => $step ] as $key => $attr )
      {
         if( $attr !== null ) $attributes[$key] = $attr;
      }
      parent::__construct($name, $required, $fixed, $attributes, $label);
   }
}
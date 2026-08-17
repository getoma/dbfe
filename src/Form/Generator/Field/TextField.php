<?php

namespace getoma\dbfe\Form\Generator\Field;

class TextField extends Field
{
   public function __construct(
      string $name,
      bool $required = false,
      bool $fixed = false,
      ?int $maxlength = null,
      array $attributes = [],
      ?string $label = null,
   )
   {
      if( $maxlength ) $attributes['maxlength'] = $maxlength;
      parent::__construct($name, $required, $fixed, $attributes, $label);
   }
}
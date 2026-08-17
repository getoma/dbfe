<?php

namespace getoma\dbfe\Form\Generator\Field;

class SelectField extends Field
{
   public function __construct(
      string $name,
      private readonly array $options = [],
      private readonly array $disabledKeys = [],
      bool $required = false,
      bool $fixed = false,
      array $attributes = [],
      ?string $label = null,
   )
   {
      parent::__construct($name, $required, $fixed, $attributes, $label);
   }

   public function toArray(): array
   {
      return [ 'options' => $this->options, 'disabled_keys' => $this->disabledKeys ] + parent::toArray();
   }
}
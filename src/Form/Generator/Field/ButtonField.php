<?php declare(strict_types=1);

namespace getoma\dbfe\Form\Generator\Field;

/**
 * support adding of additional buttons inside a formular
 */
class ButtonField extends Field
{
   public function __construct(
      string $name,
      private readonly mixed $defaultValue = '1',      // define value at form generation, instead via data input
      private readonly string $button_type = 'submit', // HTML button type, e.g. submit/reset/button
      array $attributes = [],
      ?string $label = null,
   )
   {
      parent::__construct($name, false, false, $attributes, $label);
   }

   public function toArray(): array
   {
      $result = parent::toArray();
      $result['button_type'] = $this->button_type;
      return $result;
   }

   public function infer(array $values, array $errors = [], string $field_id = ''): array
   {
      $result = parent::infer($values, $errors, $field_id);
      $result['value'] ??= $this->defaultValue;
      return $result;
   }

}
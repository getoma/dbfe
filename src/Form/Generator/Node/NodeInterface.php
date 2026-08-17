<?php

namespace getoma\dbfe\Form\Generator\Node;

interface NodeInterface
{
   /* type of a node - directly derived from class name */
   public static function type(): string;

   /* name of a specific field */
   public function name(): string;

   /* convert node definition into an array - without actual data or errors */
   public function toArray(): array;

   /* infer node structure with provided form data and provide result as array */
   public function infer(array $values, array $errors = [], string $field_id = ''): array;
}
<?php

namespace getoma\dbfe\Form\Generator\Field;

use getoma\dbfe\Table\Column\FileContentType;
use getoma\dbfe\Util\FileHandler\FileHandlerIf;

class FileField extends Field
{
   public function __construct(
      string $name,
      private readonly FileHandlerIf $fh,
      private readonly FileContentType $content_type = FileContentType::Opaque,
      string $accept = '',
      private readonly string $delete_name = '',
      bool $required = false,
      bool $fixed = false,
      array $attributes = [],
      ?string $label = null,
   )
   {
      if( $accept ) $attributes['accept'] = $accept;
      parent::__construct($name, $required, $fixed, $attributes, $label);
   }

   public function toArray(): array
   {
      $result = parent::toArray();
      $result['file_content_type'] = $this->content_type->name;
      return $result;
   }

   public function infer(array $values, array $errors = [], string $field_id = ''): array
   {
      $result = parent::infer($values, $errors, $field_id);

      if( $id = $result['value']??null )
      {
         $result['stored_file'] = [
            'id'   => $id,
            'name' => $this->fh->getFileName($id),
            'url'  => $this->fh->getFileUrl($id),
         ];

         if( $this->delete_name ) $result['delete_name'] = $this->delete_name;
      }

      return $result;
   }
}
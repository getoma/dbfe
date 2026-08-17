<?php

namespace getoma\dbfe\Form\Generator\Node;

use getoma\dbfe\Form\Generator\Field\BooleanField;

class ArrayGroupNode extends CompositeNode
{
   public function __construct(
      string $name,
      array $attributes = [],
      private readonly string $delete_name = '',
      private readonly bool $addScaffoldRow = true,
      ?string $label = null,
   )
   {
      parent::__construct($name, $attributes, $label);
   }

   public function toArray(): array
   {
      $result = parent::toArray();
      $result['has_delete_column'] = !empty($this->delete_name);
      $result['is_scaffolded'] = $this->addScaffoldRow;
      return $result;
   }

   public function infer(array $values, array $errors = [], string $field_id = ''): array
   {
      $result = $this->toArray();
      $result['columns'] = [];
      $result['rows'] = [];

      $rowCount = 0;
      foreach( $this->children() as $child )
      {
         $key = $child->name();
         if( isset($values[$key]) && is_array($values[$key]) )
         {
            $rowCount = max($rowCount, count($values[$key]));
         }
         $result['columns'][] = $child->toArray();
      }

      $delbox = null;
      if( $this->delete_name )
      {
         $delbox = new BooleanField($this->delete_name);
         $result['columns'][] = $delbox->toArray();
      }

      for( $row_idx = 0; $row_idx < $rowCount; $row_idx++ )
      {
         $sub_id = $field_id . "[$row_idx]";
         $row = [];
         foreach( $this->children() as $child )
         {
            $key = $child->name();

            $value = $values[$key] ?? null;
            $rowValues = [$key => is_array($value)? ($value[$row_idx] ?? null) : $value];

            $error = $errors[$key] ?? null;
            $rowErrors = [$key => is_array($error)? ($error[$row_idx] ?? null) : $error];

            $inferred = $child->infer($rowValues, $rowErrors, $sub_id);

            $row[] = $inferred;
         }

         if( $delbox )
         {
            $row[] = $delbox->infer([], [], $sub_id);
         }

         $result['rows'][] = $row;
      }

      if( $this->addScaffoldRow )
      {
         /**
          * scaffold row allows to add new entries to this array group
          * sub elements need their "fixed" and "required" attributes removed, as there is
          * no data for this row existing that could be fixed, and it must be allowed to keep
          * the new row empty
          */
         $scaffoldRow = [];
         foreach( $this->children() as $child )
         {
            $field = $child->infer([], [], $field_id . "[]");
            if( isset($field['attributes']['disabled']) ) unset($field['attributes']['disabled']);
            if( isset($field['attributes']['required']) ) unset($field['attributes']['required']);
            $scaffoldRow[] = $field;
         }
         $result['rows'][] = $scaffoldRow;
      }

      return $result;
   }
}
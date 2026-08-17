<?php

namespace getoma\dbfe\Form\Generator;

use getoma\dbfe\Form\Generator\Field\FileField;
use getoma\dbfe\Form\Generator\Node\CompositeNode;

final readonly class InputMask implements \JsonSerializable
{
   private array $inferred;
   private bool  $hasFileFields;

   public function __construct(
      CompositeNode $content,
      array $values,
      array $errors,
   )
   {
      $this->inferred = $content->infer( $values, $errors );

      /* check if any child represents a file upload field */
      $hasFileFields = false;
      $children = $content->children();
      while( $child = array_shift($children) )
      {
         if( $child instanceof CompositeNode )
         {
            array_push($children, ...$child->children());
         }
         elseif( $child instanceof FileField )
         {
            $hasFileFields = true;
            break;
         }
         /* else nothing to do */
      }
      $this->hasFileFields = $hasFileFields;
   }

   public function hasUploads(): bool
   {
      return $this->hasFileFields;
   }

   public function toArray(): array
   {
      return $this->inferred;
   }

   public function jsonSerialize(): mixed
   {
      return $this->inferred;
   }
}
<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Validator\Constraint\ConstraintIf;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;

/**
 * Handle db column text types:
 * char, varchar, text
 */
class Text extends Type
{
   protected $maxlen = null;

   public function __construct(string $type, $null)
   {
      parent::__construct( $type, $null );

      $matches = null;
      if( preg_match( "/char\\((\\d+)\\)/", $type, $matches ) )
      {
         $this->maxlen = $matches[1];
      }
   }

   /**
    * remove the string limiters from default value as provided from DB
    * @return mixed
    */
   public function getDefault($db_default = ''): mixed
   {
      return is_string($db_default)? trim( $db_default, "\"'") : $db_default;
   }

   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      if( isset( $this->maxlen ) )
      {
         return [ 'type' => 'text', 'size' => $this->maxlen ];
      }
      else
      {
         return [ 'type' => 'textarea' ];
      }
   }

   public function getConstraint(): ?ConstraintIf
   {
      return null;
   }
}

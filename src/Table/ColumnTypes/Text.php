<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Generator\Node\NodeInterface;
use Respect\Validation\Validator as V;

/**
 * Handle db column text types:
 * char, varchar, text
 */
class Text extends Type
{
   protected ?int $maxlen = null;

   public function __construct(string $type, mixed $null)
   {
      parent::__construct( $type, $null );

      $matches = null;
      if( preg_match( "/char\\((\\d+)\\)/", $type, $matches ) )
      {
         $this->maxlen = (int)$matches[1];
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

   public function getFormNode(string $name, bool $required = false, bool $fixed = false, array $attributes = []): NodeInterface
   {
      return $this->maxlen
         ? new \getoma\dbfe\Form\Generator\Field\TextField($name, $required, $fixed, $this->maxlen, $attributes)
         : new \getoma\dbfe\Form\Generator\Field\TextareaField($name, $required, $fixed, $attributes);
   }

   public function getConstraint(): V
   {
      $rule = V::stringVal();
      if( $this->maxlen ) $rule = $rule->length(max: $this->maxlen);
      return $rule;
   }
}

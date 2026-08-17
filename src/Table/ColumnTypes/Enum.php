<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Form\Generator\Node\NodeInterface;
use getoma\dbfe\Form\Generator\Field\SelectField;
use Respect\Validation\Validator as V;

/**
 * Handle db column enum type:
 */
class Enum extends Type
{
   protected $values = [ ];

   public function __construct(string $type, $null)
   {
      parent::__construct( $type, $null );

      $out = null;
      if( preg_match_all( "/'([^']*)'/", $type, $out, PREG_PATTERN_ORDER ) )
      {
         foreach( $out[1] as $entry )
         {
            $this->values[$entry] = $entry;
         }
      }
   }

   public function getFormNode(string $name, bool $required = false, bool $fixed = false, array $attributes = []): NodeInterface
   {
      return new SelectField(
         $name,
         $this->values,
         $attributes['disabled_keys'] ?? [],
         $required,
         $fixed,
         $attributes,
      );
   }

   public function getConstraint(): V
   {
      return V::in( array_keys( $this->values ) );
   }
}

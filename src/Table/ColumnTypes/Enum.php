<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
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

   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      $values = array_map( function ($value) use ($lblHdl,$prefix)
      {
         return $lblHdl->get( $value, $prefix );
      }, $this->values );

      return [ 'type' => 'select', 'selection' => array_merge( [''=>'N/A'], $values ) ];
   }

   public function getConstraint(): V
   {
      return V::in( array_keys( $this->values ) );
   }
}

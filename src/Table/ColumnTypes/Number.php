<?php

namespace getoma\dbfe\Table\ColumnTypes;

use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use Respect\Validation\Validator as V;

/**
 * Handle db column number types:
 * integer, decimal, floating point
 */
class Number extends Type
{
   /** @var Number */
   protected $min = null;
   /** @var Number */
   protected $max = null;
   /** @var bool */
   protected $is_float = false;

   public function __construct(string $type, $null)
   {
      parent::__construct( $type, $null );

      if( $this->_parseAsInt( $type )     or
          $this->_parseAsDecimal( $type ) or
          $this->_parseAsFloat( $type ) )
      {
         // ok :-)
      }
      else
      {
         /* throw exception here, may be handled in a derived class
          * or whatever
          */
         throw new TypeException( "unsupported number type $type" );
      }
   }

   private function _parseAsInt(string $type)
   {
      $inttypes = [
            'tinyint' => 1,
            'smallint' => 2,
            'mediumint' => 3,
            'int' => 4,
            'bigint' => 8
      ];

      foreach( $inttypes as $name => $bytes )
      {
         if( strpos( $type, $name ) === 0 )
         {
            // find number of symbols
            $num_symbols = 0;
            $matches = null;
            if( preg_match( "/int\\((\\d+)\\)/", $type, $matches ) )
            {
               $num_symbols = $matches[1];
            }

            // check for 'unsigned'
            $signed = (false === strpos( $type, 'unsigned' ));

            // calculate max value according int type
            $type_max = 2 ** ($bytes * 8 - $signed) - 1;
            // calculate max value according number of symbols
            $sym_max = 10 ** ($num_symbols) - 1;

            // take the lower as maximum
            $this->max = min( [
                  $type_max,
                  $sym_max
            ] );
            // set minimum (symmetrical to keep it easy)
            $this->min = $signed ? -$this->max : 0;

            $this->is_float = false;

            return true;
         }
      }

      // no integer type
      return false;
   }

   private function _parseAsDecimal(string $type)
   {
      $matches = null;
      if( preg_match( "/(?:decimal|float|double)\\((\\d+),(\\d+)\\)/", $type, $matches ) )
      {
         $num_digits = $matches[1];
         $num_frac   = $matches[2];

         // check for 'unsigned'
         $signed = (false === strpos( $type, 'unsigned' ));

         // calculate max value according number of digits
         $this->max = (10**$num_digits - 1) / (10**$num_frac);
         // set min according signedness
         $this->min = $signed ? -$this->max : 0;

         $this->is_float = true;

         return true;
      }
      return false;
   }

   private function _parseAsFloat(string $type)
   {
      foreach( [ 'float', 'double' ] as $_t )
      {
         if( 0 === strpos( $type, $_t ) )
         {
            $this->is_float = true;
            return true;
         }
      }
      return false;
   }

   public function getFormAttributes(?LabelHandlerIf $lblHdl = null, string $prefix = ''): array
   {
      $result = [ 'type' => 'number' ];

      if( isset( $this->min ) )
      {
         $result['min'] = $this->min;
      }

      if( isset( $this->max ) )
      {
         $result['max'] = $this->max;
      }

      if( !$this->is_float )
      {
         $result['step'] = 1;
      }

      return $result;
   }

   public function getConstraint(): V
   {
      return $this->is_float
         ? V::numericVal()->between( $this->min, $this->max )
         : V::intVal()->between( $this->min, $this->max );
   }
}

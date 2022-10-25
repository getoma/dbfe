<?php namespace dbfe;

require_once 'dbfe/Form/Validator/Constraint.php';
require_once 'dbfe/Util/LabelHandler.php';
require_once 'dbfe/Util/Exception.php';

class TypeException extends DatabaseError {};
 
interface TypeIf
{
   /**
    * whether the value can be NULL
    * @return bool
    */
   public function isNullOk();

   /**
    * allow overriding of database default in input processing
    * @return mixed
    */
   public function getDefault($db_default = '');

   /**
    * @return array[mixed]
    */
   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '');

   /**
    * @return Form\Validator\Constraint
    */
   public function getConstraint();
}

/**
 * base class to handle db column data types
 * The different type families (numeric, text, ...) are handled
 * by specialised sub classes
 */
abstract class Type implements TypeIf
{
   /**
    * the original type specification
    * @var string
    */
   protected $m_type;

   /** whether the field can be null
    * @var bool
    */
   protected $m_null;

   public function __construct(string $type, $null)
   {
      $this->m_type = $type;
      if( $null === "NO" ) $this->m_null = false;
      else $this->m_null = (bool)$null;
   }

   /**
    *
    * @return boolean
    */
   public function isNullOk()
   {
      return $this->m_null;
   }

   /**
    * allow overriding of database default in input processing
    * @return mixed
    */
   public function getDefault($db_default = '')
   {
      return $db_default;
   }

   /**
    * Factory to create specialised classes
    */
   static public function create(string $type, $null, string $name, bool $heuristic = true)
   {
      /** heuristic rules to find the right class according type or name prefix */
      $heuristic_factory = [
            [ 'type' => 'tinyint(1)', 'class' => 'Boolean'  ],
            [ 'name' => 'email'     , 'class' => 'Email'    ],
            [ 'name' => 'tel'       , 'class' => 'Phone'    ],
            [ 'name' => 'phone'     , 'class' => 'Phone'    ],
            [ 'name' => 'mobil'     , 'class' => 'Phone'    ],
            [ 'name' => 'homepage'  , 'class' => 'Uri'      ],
            [ 'name' => 'url'       , 'class' => 'Uri'      ],
      ];

      /** natural rules to find the right class from the column type */
      $type_factory = [
            'enum'      => 'Enum',
            'int'       => 'Number',
            'decimal'   => 'Number',
            'char'      => 'Text',
            'text'      => 'Text',
            'datetime'  => 'DateTime',
            'timestamp' => 'DateTime',
            'time'      => 'Time',
            'date'      => 'Date',
            'year'      => 'Year',
            'float'     => 'Number',
            'double'    => 'Number',
            'blob'      => 'Binary',
            // (todo) bit type
      ];

      /** short-cut function to create the type class from its name */
      $class = function($cname) use ($type,$null)
      {
         $class = __NAMESPACE__ . "\\" . $cname;
         return new $class( $type, $null );
      };

      /** evaluate heuristic rules if allowed */
      if( $heuristic )
      {
         $name = strtolower($name);
         foreach( $heuristic_factory as $rule )
         {
            /* check every rule that is set whether it fits */
            if( isset($rule['type'])
              &&(strpos($type, $rule['type'])===false))
            {
               /* type rule set but doesn't fit */
               continue;
            }
            if( isset($rule['name'])
              &&(strpos($name, $rule['name'])!==0))
            {
               /* name rule set but doesn't fit */
               continue;
            }
            /* all rules that are set do fit - create class and return */
            try
            {
               return $class($rule['class']);
            }
            catch( TypeException $e )
            {
               /* a heuristic misfired - only print a warning, and try to go on */
               trigger_error($e->getMessage(), E_USER_WARNING );
            }
         }
      }

      /** evaluate natural rules */
      foreach( $type_factory as $keyword => $cname )
      {
         if( strpos( $type, $keyword ) !== false )
         {
            return $class( $cname );
         }
      }

      die( "unsupported type $type" );

      return null;
   }
}

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

   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '')
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

   public function getConstraint()
   {
      return $this->is_float? fvc\Number($this->min, $this->max) : fvc\Integer($this->min, $this->max);
   }
}

/**
 * Handle db column date type:
 */
class Date extends Type
{
   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '')
   {
      return [ 'type' => 'date' ];
   }

   public function getConstraint()
   {
      return fvc\Date();
   }
}

/**
 * Handle db column date type:
 */
class Year extends Type
{
   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '')
   {
      return [ 'type' => 'Number', 'class' => 'InputYear', 'min' => 1900, 'step' => 1, 'max' => 9999 ];
   }

   public function getConstraint()
   {
      return fvc\Integer(1900,9999);
   }
}

/**
 * Handle db column Time type
 */
class Time extends Type
{
   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '')
   {
      return [ 'type' => 'time' ];
   }

   public function getConstraint()
   {
      return fvc\Time();
   }
}

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
   public function getDefault($db_default = '')
   {
      return is_string($db_default)? trim( $db_default, "\"'") : $db_default;
   }

   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '')
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

   public function getConstraint()
   {
      return null;
   }
}

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

   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '')
   {
      $values = array_map( function ($value) use ($lblHdl,$prefix)
      {
         return $lblHdl->get( $value, $prefix );
      }, $this->values );

      return [ 'type' => 'select', 'selection' => array_merge( [''=>'N/A'], $values ) ];
   }

   public function getConstraint()
   {
      return fvc\Set(array_keys($this->values));
   }
}

class Binary extends Type
{
   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '')
   {
      return [];
   }

   public function getConstraint()
   {
      return [];
   }
}

/**
 * Handle db column boolean types:
 * tinyint(1)
 */
class Boolean extends Type
{

   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '')
   {
      return [ 'type' => 'checkbox', 'value' => '1' ];
   }

   public function getConstraint()
   {
      return fvc\Integer( 0 );
   }

   /**
    *
    * {@inheritdoc}
    * @see Type::is_null_ok()
    */
   public function isNullOk()
   {
      return false;
   }
}

class Phone extends Type
{
   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '')
   {
      return [ 'type' => 'tel' ];
   }

   public function getConstraint()
   {
      return fvc\Telephone();
   }
}

class Email extends Text
{
   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '')
   {
      $result = [ 'type' => 'email' ];
      if( isset($this->maxlen) ) $result['size'] = $this->maxlen;
      return $result;
   }

   public function getConstraint()
   {
      return fvc\Email();
   }
}

class Uri extends Text
{
   public function getFormAttributes(LabelHandlerIf $lblHdl = null, string $prefix = '')
   {
      $result = [ 'type' => 'url' ];
      if( isset($this->maxlen) ) $result['size'] = $this->maxlen;
      return $result;
   }

   public function getConstraint()
   {
      return parent::getConstraint(); // TODO
   }
}

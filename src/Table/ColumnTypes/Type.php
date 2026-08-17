<?php

namespace getoma\dbfe\Table\ColumnTypes;

/**
 * base class to handle db column data types
 * The different type families (numeric, text, ...) are handled
 * by specialised sub classes
 */
abstract class Type implements TypeIf
{
   /**
    * the original type specification
    */
   protected string $type;

   /**
    * whether the field can be null
    */
   protected bool $null;

   public function __construct(string $type, mixed $null)
   {
      $this->type = $type;
      if( $null === "NO" ) $this->null = false;
      else $this->null = (bool)$null;
   }

   /**
    * whether NULL is a valid value
    */
   public function isNullOk(): bool
   {
      return $this->null;
   }

   /**
    * allow overriding of database default in input processing
    */
   public function getDefault($db_default = ''): mixed
   {
      return $db_default;
   }

   protected function without(array $attributes, array $keys): array
   {
      foreach( $keys as $key )
      {
         unset($attributes[$key]);
      }

      return $attributes;
   }

   /**
    * Factory to create specialised classes
    */
   public static function create(string $type, $null, string $name, bool $heuristic = true)
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

      throw new \DomainException( "unsupported type $type" );
   }
}

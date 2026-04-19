<?php

namespace getoma\dbfe\Form\Validator\Constraint;

/**
 * check if value looks like a certain type of date
 */
class Date extends Constraint
{
   private static $Formats = [
      'generic' => '^\s*\d\d(?:\d\d)?[-\/.]\d\d(?:\d\d)?[-\/.]\d\d(?:\d\d)?\s*',
      'german' => '\d\d?\.\d\d?\.\d\d(?:\d\d)?' ];

   function __construct(string $format = 'generic', string $name = null)
   {
      if( !array_key_exists($format, static::$Formats) )
      {
         throw new \BadMethodCallException("unknown date format '$format'");
      }

      parent::__construct('/^\s*' . static::$Formats[$format] . '\s*$/', $name);
   }
}

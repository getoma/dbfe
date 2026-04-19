<?php

namespace getoma\dbfe\Form\Validator\Constraint;

/* fast-constructors: */
final class FastConstructors
{
   public static function RegExp( $re, $name=null )                  { return new Constraint( $re, $name ); }
   public static function Integer( $min=null, $max=null, $name=null) { return new Integer( $min, $max, $name ); }
   public static function Number( $min=null, $max=null, $name=null ) { return new Number($min,$max,$name); }
   public static function Length( $min=null, $max=null, $name=null ) { return new Length( $min, $max, $name ); }
   public static function Currency($name=null)                       { return new Currency($name); }
   public static function Email($name=null)                          { return new Email($name); }
   public static function Telephone($name=null)                      { return new Telephone($name); }
   public static function Date($format='generic', $name=null)        { return new Date($format, $name); }
   public static function Time($name=null)                           { return new Time($name); }
   public static function Set($set, $name=null)                      { return new Set($set, $name); }
   public static function Func($func, $name)                         { return new Func($func, $name); }
}
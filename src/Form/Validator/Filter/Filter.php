<?php

namespace getoma\dbfe\Form\Validator\Filter;

/* generic base filter class, just calls the function given via the constructor */
class Filter implements FilterInterface
{
   /**@var callable */
   private $func;
   /**@var array */
   private $params;

   function __construct(callable $func, array $params)
   {
      $this->func   = $func;
      $this->params = $params;
   }

   public function execute($value)
   {
      return call_user_func_array($this->func, array_merge([
         $value], $this->params));
   }

   /*
    * generic constructor to use via the interface. transparently generates the right
    * derived class
    */
   final static public function create($filter, $params = [])
   {
      $result = null;

      if( !is_callable($filter) )
      {
         /* generate possible class name */
         $class = '\\' . __NAMESPACE__ . '\\' . $filter;
         if( class_exists($class, false) )
         {
            /* create and return class */
            $result = new $class($params);
         }
      }

      if( !isset($result) )
      {
         /* fallback: try given filter as plain function name */
         $class = __CLASS__;
         $result = new $class($filter, $params);
      }

      return $result;
   }
}

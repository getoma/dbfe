<?php namespace dbfe\Form\Validator;

/**
 * general interface for usage as base
 * not all filter classes need the functionallity of the base class 'Filter'
 */
interface FilterInterface
{
   public function execute($value);
}

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

/**
 * calls htmlspecialchars with predefined parameters 
 */
class html extends Filter
{
   function __construct($params)
   {
      parent::__construct('htmlspecialchars', [ ENT_COMPAT | ENT_XHTML, 'UTF-8']);
   }
}

/**
 * turns a german date into a mysql date 
 */
class mysqldate implements FilterInterface
{
   public function execute($value)
   {
      $date = explode('.', $value);
      return sprintf('%04d-%02d-%02d', $date[2], $date[1], $date[0]);
   }
}

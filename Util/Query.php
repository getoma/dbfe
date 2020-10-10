<?php namespace dbfe;

require_once 'dbfe/Util/Exception.php';

interface QueryBuilderIf
{
   /**
    * @return string
    */
   function asString();
}

class QueryException extends \LogicException {};

class QueryBuilderUtil
{
   static public function generateFilter( $filter )
   {
      if( count($filter) )
      {
         // generate "key=value" pairs as strings
         $whereList = array_map( function ($k, $v)
         {
            if( is_numeric($k) ) // numeric key -> filter condition is given completely
            {
               return $v;
            }
            else if( ($v === 'null') || $v === 'not null' )
            {
               return "$k is $v";
            }
            else if( is_null($v) )
            {
               return "$k is null";
            }
            else if( is_bool($v) )
            {
               return "$k=" . ($v+0);
            }
            else
            {
               return "$k=$v";
            }
         },
         array_keys( $filter ), $filter );
         
         // combine the where clause
         return ' where ' . join( ' and ', $whereList );
      }
      else
      {
         return '';
      }
   }   
}

class SelectQuery implements QueryBuilderIf
{
   /** @var string */
   public $table_spec  = null;
   /** @var array[string] */
   public $columns     = [];
   /** @var array[string] */
   public $filter      = [];
   /** @var array[string] */
   public $order       = [];
   /** @var array[string] */
   public $group       = [];
   /** @var bool */
   public $distinct    = false;
   /** @var int */
   public $limit       = null;

   public function asString()
   {
      $query = 'select';

      if( $this->distinct )
      {
         $query .= ' distinct';
      }

      if( count($this->columns) )
      {
         $query .= ' ' . join(",", $this->columns);
      }
      else
      {
         $query .= ' *';
      }

      $query .= ' from ' . $this->table_spec;

      $query .= QueryBuilderUtil::generateFilter($this->filter);

      if( !empty($this->group) )
      {
         $query .= ' group by ' . (is_array($this->group)? join( ',', $this->group ) : $this->group);
      }

      if( !empty($this->order) )
      {
         $query .= ' order by ' . (is_array($this->order)? join( ',', $this->order ) : $this->order);
      }

      if( isset($this->limit) )
      {
         $query .= ' limit ' . $this->limit;
      }

      return $query;
   }

}

class InsertQuery implements QueryBuilderIf
{
   /** @var string */
   public $table_spec  = null;
   /** @var array[string] */
   public $columns     = [];
   /** @var mixed */
   public $on_duplicate = false;
   /** @var bool */
   public $replace      = false;

   public function asString()
   {
      $query = sprintf( '%s into %s (%s) values (%s)'
                      , ($this->replace? 'replace' : 'insert')
                      , $this->table_spec
                      , join( ',', array_keys(  $this->columns) )
                      , join( ',', array_values($this->columns) ) );
      if( $this->on_duplicate === true )
      {
         $query .= " on duplicate key update ";
         $query .= join( ',', array_map( function ($c) { return sprintf( "%s=values(%s)", $c, $c ); }, array_keys($this->columns) ) );
      }
      else if( $this->on_duplicate === false )
      {
         /* nothing */
      }
      else
      {
         throw new QueryException('complex "on_duplicate" not yet supported');
      }

      return $query;
   }
}

class UpdateQuery implements QueryBuilderIf
{
   /** @var string */
   public $table_spec  = null;
   /** @var array[string] */
   public $columns     = [];
   /** @var array[string] */
   public $filter      = [];

   public function asString()
   {
      if( empty($this->filter) )
      {
         throw new QueryException('attempt to generate update query without where-clause!');
      }

      return sprintf( 'update %s set %s %s'
                    , $this->table_spec
                    , join( ',', array_map( function($k,$v) { return "$k=$v"; }, array_keys($this->columns), $this->columns ) )
                    , QueryBuilderUtil::generateFilter($this->filter) );
   }
}

class DeleteQuery implements QueryBuilderIf
{
   /** @var string */
   public $table_spec    = null;
   /** @var array[string] */
   public $target_tables = [];
   /** @var array[string] */
   public $filter        = [];

   public function asString()
   {
      $query = 'delete';

      if( count($this->target_tables) )
      {
         $query .= ' ' . join(",", $this->target_tables);
      }

      $query .= ' from ' . $this->table_spec;

      $query .= QueryBuilderUtil::generateFilter($this->filter);

      return $query;
   }
}

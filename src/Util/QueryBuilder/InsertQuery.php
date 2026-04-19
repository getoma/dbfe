<?php

namespace getoma\dbfe\Util\QueryBuilder;

use getoma\dbfe\Util\Exception\QueryException;

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

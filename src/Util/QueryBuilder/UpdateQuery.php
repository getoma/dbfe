<?php

namespace getoma\dbfe\Util\QueryBuilder;

use getoma\dbfe\Util\Exception\QueryException;

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
                    , empty($this->filter)? '' : 'where '.QueryBuilderUtil::generateFilter($this->filter) );
   }
}

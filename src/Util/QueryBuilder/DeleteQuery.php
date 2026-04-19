<?php

namespace getoma\dbfe\Util\QueryBuilder;

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

      if( !empty($this->filter) )
      {
         $query .= ' where ' . QueryBuilderUtil::generateFilter($this->filter);
      }

      return $query;
   }
}

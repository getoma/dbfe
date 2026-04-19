<?php

namespace getoma\dbfe\Util\QueryBuilder;

class SelectQuery implements QueryBuilderIf
{
   /** @var string */
   public $table_spec  = null;
   /** @var array[string] */
   public $columns     = [];
   /** @var array[string] */
   public $filter      = [];
   /** @var array[string] */
   public $having      = [];
   /** @var array[string] */
   public $order       = [];
   /** @var array[string] */
   public $group       = [];
   /** @var bool */
   public $distinct    = false;
   /** @var int */
   public $limit       = null;
   /** @var SelectQuery */
   public $union       = null;

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

      if( !empty($this->filter) )
      {
         $query .= ' where ' . QueryBuilderUtil::generateFilter($this->filter);
      }

      if( !empty($this->group) )
      {
         $query .= ' group by ' . (is_array($this->group)? join( ',', $this->group ) : $this->group);
      }

      if( !empty($this->having) )
      {
         $query .= ' having ' . QueryBuilderUtil::generateFilter($this->having);
      }

      if( isset($this->union) )
      {
         $query .= ' union ' . $this->union->asString();
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

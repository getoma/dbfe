<?php

namespace getoma\dbfe\Table;

/**
 * class to manage a (foreign) reference to another table
 */
class TableReference
{
   function __construct(
      public readonly TableIf $table,    // name of the other table
      public readonly string  $column,   // name of the column that holds the reference in the other table
      public readonly string  $refcolumn // name of the referenced column in your table
   )
   {
   }

   /**
    * check whether the reference is "one to one" or "one to many"
    * if the referenced table has only one primary key column, and
    * this column is also the reference column, then this is probably
    * a 1-to-1 reference.
    * Otherwise, 1-to-many is assumed
    * also the column
    */
   public function isOne2Many(): bool
   {
      $keys = array_keys( $this->table->getPrimaryKey() );
      return !( (count($keys) === 1) && ($keys[0] === $this->column) );
   }

   /**
    * if the table a 1:1 dependency, return a name that can be used
    * to provide a selection checkbox for whether this sub table shall be used
    * return null if it's a 1:many dependency
    */
   public function getSelectionName(): ?string
   {
      return $this->isOne2Many()? null : 'select_' . $this->table->getName();
   }
}

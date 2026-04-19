<?php

namespace getoma\dbfe\Table;

/**
 * class to manage a (foreign) reference to another table
 */
class TableReference
{
   /** @var Table name of the other table*/
   public $table;
   /** @var string name of the column that holds the reference in the other table */
   public $column;
   /** @var string name of the referenced column in your table */
   public $refcolumn;

   /**
    * @param Table $table      link to the other table that holds a reference
    * @param string $column    name of the column that holds the reference in the other table
    * @param string $refcolumn name of the referenced column in your table
    */
   function __construct( TableIf $table, string $column, string $refcolumn )
   {
      $this->table     = $table;
      $this->column    = $column;
      $this->refcolumn = $refcolumn;
   }

   /**
    * check whether the reference is "one to one" or "one to many"
    * if the referenced table has only one primary key column, and
    * this column is also the reference column, then this is probably
    * a 1-to-1 reference.
    * Otherwise, 1-to-many is assumed
    * also the column
    * @return boolean
    */
   public function isOne2Many()
   {
      $keys = array_keys( $this->table->getPrimaryKey() );
      return !( (count($keys) === 1) && ($keys[0] === $this->column) );
   }

   /**
    * if the table a 1:1 dependency, return a name that can be used
    * to provide a selection checkbox for whether this sub table shall be used
    * return null if it's a 1:many dependency
    */
   public function getSelectionName()
   {
      return $this->isOne2Many()? null : 'select_' . $this->table->getName();
   }
}

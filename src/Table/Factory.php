<?php

namespace getoma\dbfe\Table;

use Aura\SqlQuery\QueryFactory;

class Factory
{
   /** @var Table[] */
   private array $tables = [];

   /**
    * constructor
    */
   function __construct(
      protected readonly \PDO $dbh,
      protected readonly QueryFactory $query_factory,
      protected readonly bool $heuristic_column_types = true
   )
   {
   }

   /**
    * load/register a database table
    */
   public function loadTable(string|Table $table, bool $link_references = false, array $visited = [] ): Table
   {
      if( $table instanceof Table )
      {
         $this->tables[$table->getName()] = $table;
         return $table;
      }
      else if( is_string($table) )
      {
         if( !isset( $this->tables[$table] ) )
         {
            $options = Table::NO_REFERENCES; // link references in a separate step to avoid endless recursion in case there are cyclic references
            if( $link_references )               $options |= Table::BIDIRECTIONAL_REFERENCES;
            if( !$this->heuristic_column_types ) $options |= Table::NO_HEURISTIC_TYPES;
            $this->tables[$table] = new Table( $this, $this->dbh, $this->query_factory, $table, $options );
            $this->tables[$table]->linkReferences($this, $options, array_merge($visited, [$table]));
         }
         return $this->tables[$table];
      }
      else
      {
         throw new \LogicException('invalid type of table at loadTable');
      }
   }
}

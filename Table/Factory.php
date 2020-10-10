<?php namespace dbfe;

require_once 'dbfe/Table/Table.php';

class Factory
{
   /** @var \PDO */
   protected $m_dbh = null;

   /** @var Table */
   private $m_tables = [];

   /** @var boolean */
   protected $m_heuristic_column_types;

   /**
    * @param \PDO $hdl
    */
   function __construct(\PDO $dbh, bool $heuristic_column_types = true )
   {
      $this->m_dbh = $dbh;
      $this->m_heuristic_column_types = $heuristic_column_types;
   }

   /**
    * @param $table
    * @param bool $link_references whether to inform other tables about references to them
    * @return Table
    */
   public function loadTable($table, bool $link_references = false, array $visited = [] )
   {
      if( $table instanceof Table )
      {
         $this->m_tables[$table->getName()] = $table;
         return $table;
      }
      else if( is_string($table) )
      {
         if( !isset( $this->m_tables[$table] ) )
         {
            $table_struc = $this->m_dbh->query( 'explain ' . $table )->fetchAll();
            $options = Table::NO_REFERENCES; // link references in a separate step to avoid endless recursion in case there are cyclic references
            if( $link_references )                 $options |= Table::BIDIRECTIONAL_REFERENCES;
            if( !$this->m_heuristic_column_types ) $options |= Table::NO_HEURISTIC_TYPES;
            $this->m_tables[$table] = new Table( $this, $this->m_dbh, $table, $table_struc, $options );
            $visited[] = $table; // note down this table as visited in the current stacking
            $this->m_tables[$table]->linkReferences($this, $options, $visited);
         }
         return $this->m_tables[$table];
      }
      else
      {
         throw \LogicException('invalid type of table at loadTable');
      }
   }
}

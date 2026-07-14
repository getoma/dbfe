<?php

namespace getoma\dbfe\Table;

use getoma\dbfe\Form\Printer\Configuration\ConfigurationIf;
use getoma\dbfe\Form\Printer\Configuration\ConfigurationListIf;
use getoma\dbfe\Table\Column\DispType;
use getoma\dbfe\Table\Column\FileHandlerColumn;
use getoma\dbfe\Table\Column\PlainColumn;
use getoma\dbfe\Table\Column\ColumnIf;
use getoma\dbfe\Table\Column\ReferenceColumn;
use getoma\dbfe\Table\Column\ReferenceColumnIf;
use getoma\dbfe\Table\Column\SelectionColumn;
use getoma\dbfe\Util\Exception\DatabaseError;
use getoma\dbfe\Util\Exception\DatabaseUpdateError;
use getoma\dbfe\Util\FileHandler\FileHandlerIf;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use getoma\dbfe\Util\QueryBuilder\DeleteQuery;
use getoma\dbfe\Util\QueryBuilder\InsertQuery;
use getoma\dbfe\Util\QueryBuilder\SelectQuery;
use getoma\dbfe\Util\QueryBuilder\UpdateQuery;

class Table implements TableIf
{
   /** @var string */
   protected $m_name;

   /** @var ColumnIf[] */
   protected array $m_columns = [];

   /** @var ColumnIf[] */
   protected array $m_primKeys = [];

   /** @var ColumnIf[] */
   protected array $m_unique = [];

   /** @var FileHandlerColumn[] */
   protected array $m_filehdl = [];

   /** @var \PDO */
   protected \PDO $m_dbh;

   /** @var TableReference[] */
   protected array $m_extRef = [];

   /** @var string[] */
   protected array $order = [];

   /** @var string[] */
   protected array $filter = [];

   /** @var bool */
   protected bool $fieldsets_for_references = false;

   /** @var ?int */
   protected ?int $last_insert_id = null;

   /**
    * @param Factory $factory
    * @param \PDO $dbh
    * @param string $name
    * @param array $structure
    * @param mixed $options any of BIDIRECTIONAL_REFERENCES | NO_HEURISTIC_TYPES | NO_REFERENCES
    * @throws \Exception
    */
   public function __construct(Factory $factory, \PDO $dbh, string $name, array $structure, $options = 0 )
   {
      $this->m_name = $name;
      $this->m_dbh  = $dbh;

      /* traverse through all columns of the table and process them */
      foreach( $structure as $col )
      {
         $obj = new PlainColumn( $col, $this->getName(), !($options & self::NO_HEURISTIC_TYPES)  );

         $this->m_columns[$obj->getName()] = $obj;
         if( $obj->isPrimaryKey() ) $this->m_primKeys[$obj->getName()] = $obj;
         if( $obj->isUnique()     ) $this->m_unique[$obj->getName()]   = $obj;
      }

      /* set up references to other tables */
      if( !($options & self::NO_REFERENCES) )
      {
         $this->linkReferences($factory, $options);
      }
   }

   /**
    * set up references to other tables
    * This should be done as a separate step *after* table construction
    * whenever recursive references are to be expected.
    * @param Factory $factory
    * @param mixed $options any of BIDIRECTIONAL_REFERENCES | NO_HEURISTIC_TYPES | NO_REFERENCES
    */
   public function linkReferences( Factory $factory, $options = 0, array $visited = [] ): void
   {
      /* get all references to other tables */
      $query = "select referenced_table_name, column_name, referenced_column_name
                from information_schema.key_column_usage
                where table_schema = (select database()) and
                table_name = '".$this->getName()."' and referenced_table_name is not null";
      $ref_result = $this->m_dbh->query($query);

      if( !$ref_result ) return;

      while( $row = $ref_result->fetch(\PDO::FETCH_ASSOC) )
      {
         $reftable = $factory->loadTable( $row["referenced_table_name"], false, $visited );

         /* avoid endless recursion by only allowing bidirectional links on non-visited tables */
         if( !in_array($reftable->getName(), $visited) && ($options & self::BIDIRECTIONAL_REFERENCES) )
         {
            $reftable->registerReference( new TableReference( $this, $row['column_name'], $row['referenced_column_name']) );
         }

         $obj = new ReferenceColumn( $this->getColumn($row['column_name']), $this->getName(), $reftable );

         $this->m_columns[$obj->getName()] = $obj;
         if( $obj->isPrimaryKey() ) $this->m_primKeys[$obj->getName()] = $obj;
         if( $obj->isUnique()     ) $this->m_unique[$obj->getName()]   = $obj;
      }
   }

   /**
    * {@inheritDoc}
    * @see \dbfe\TableIf::set_use_fieldset_for_references()
    */
   public function useFieldsetsForReferences( ?bool $status = null ): bool
   {
      if(isset($status)) $this->fieldsets_for_references = $status;
      return $this->fieldsets_for_references;
   }

   /**
    * register a filehandler for a certain column
    * @param string $column
    * @param FileHandlerIf $fh
    */
   public function setFilehandler( string $column, FileHandlerIf $fh, DispType $display_type = DispType::link, bool $support_delete = false ): void
   {
      if( isset( $this->m_columns[$column] ) )
      {
         $col = new FileHandlerColumn( $this->m_columns[$column], $this->getName(), $fh, $display_type, $support_delete );
         $this->m_filehdl[$column] = $col;
         $this->m_columns[$column] = $col;
      }
      else
      {
         throw new \LogicException( "unknown column $column" );
      }
   }

   /**
    * set a application-defined set of allowed input values
    * for a column
    */
   public function setValueSelection( string $column, array|SelectQuery $selection ): void
   {
      if( isset( $this->m_columns[$column] ) )
      {
         if( $selection instanceof SelectQuery )
         {
            $db_data = $this->m_dbh->query($selection->asString());
            $selection = $db_data->fetchAll( \PDO::FETCH_COLUMN );
            $selection = array_combine( $selection, $selection );
         }

         if( is_array($selection) )
         {
            $this->m_columns[$column] = new SelectionColumn( $this->m_columns[$column], $this->getName(), $selection );
         }
         else
         {
            throw new \LogicException( "invalid type of selection for column $column" );
         }
      }
      else
      {
         throw new \LogicException( "unknown column $column" );
      }
   }

   /**
    * get name of the table
    * @return string
    */
   public function getName(): string
   {
      return $this->m_name;
   }

   /**
    * get auto_increment column (if any).
    * returns null if none
    */
   public function getIdColumn(): ?ColumnIf
   {
      foreach( $this->getColumns() as $col )
      {
         if( $col->isAutoIncrement() ) return $col;
      }
      return null;
   }

   /**
    * return an array of the primary key column names
    * @return ColumnIf[]
    */
   public function getPrimaryKey(): array
   {
      return $this->m_primKeys;
   }

   /**
    * return the primary key column if there is a single one
    * throws exception, else
    * @return string
    */
   protected function getPrimaryKeyWithCheck(): string
   {
      $keys = array_keys($this->m_primKeys);
      if( count($keys) !== 1 ) throw new \LogicException('single primary key column expected for this call!');
      return $keys[0];
   }

   /**
    * get name of main "name" column.
    * This is assumed to be the first "unique" column
    * Basically this would be the "human readable" id of each row (in lieu of
    * the primary key id).
    * if no unique column found, use the primary key if only one
    * if also no primary key, return null
    */
   public function getNameColumn(): ?ColumnIf
   {
      if( !empty($this->m_unique) )
      {
         return reset($this->m_unique);
      }
      elseif( count($this->m_primKeys) == 1 )
      {
         return reset($this->m_primKeys);
      }
      else
      {
         return null;
      }
   }

   /**
    * get column of specific name
    */
   public function getColumn(string $name): ColumnIf
   {
      return $this->m_columns[$name] ?? throw new \OutOfRangeException("column does not exist: {$this->getName()}.$name");
   }

   /**
    * get all columns
    *
    * @return ColumnIf[]
    */
   public function getColumns(): array
   {
      return $this->m_columns;
   }

   /**
    * get number of columns
    */
   public function getColumnCount(): int
   {
      return count($this->m_columns);
   }

   /**
    * @return TableReference[]
    */
   public function getExternalReferences(): array
   {
      return $this->m_extRef;
   }

   /**
    * @return bool
    */
   public function hasExternalReferences(): bool
   {
      return !empty($this->m_extRef);
   }

   /**
    * get all columns EXCEPT any auto_increment column
    * @return PlainColumn[]
    */
   public function getNonIdColumns(): array
   {
      return array_filter( $this->getColumns(), function($c) { return !$c->isAutoIncrement(); } );
   }

   /**
    * get all columns EXCEPT primary key columns
    * @return PlainColumn[]
    */
   public function getNonKeyColumns(): array
   {
      return array_filter( $this->getColumns(), function($c) { return !$c->isPrimaryKey(); } );
   }

   /**
    * register a reference to another table
    * @param TableReference $ref
    */
   public function registerReference( TableReference $ref )
   {
      $this->m_extRef[$ref->table->getName()] = $ref;
   }

   /**
    * configure the output ordering of rows for this table
    */
   public function setOrdering( string|array $order ): void
   {
      if( is_string($order) ) $order = [ $order ];
      $this->order = $order;
   }

   public function hasUploads(): bool
   {
      if( !empty($this->m_filehdl) ) return true;

      foreach( $this->getExternalReferences() as $ref )
      {
         if( $ref->table->hasUploads() ) return true;
      }

      return false;
   }

   /**
    * retrieve data from this table
    */
   public function query( SelectQuery $query ): \PDOStatement
   {
      /* complete the query specification */
      if( !isset($query->table_spec) )
      {
         $query->table_spec = $this->getName();
      }

      /* execute the query */
      return $this->m_dbh->query( $query->asString() );
   }

   /**
    * check if a specific id existst in the table data
    */
   public function hasId( int $id ): bool
   {
      $result = null;

      $id_col = $this->getIdColumn();
      if( isset($id_col) )
      {
         $q = new SelectQuery();
         $q->columns    = ['count(*)'];
         $q->table_spec = $this->getName();
         $q->filter     = [ $id_col->getName() => $id ];
         if( $rc = $this->m_dbh->query($q->asString()) )
         {
            $result = ($rc->rowCount() > 0);
         }
         else
         {
            throw new \RuntimeException("could not retrieve id from database");
         }
      }

      return $result;
   }

   /**
    * get the primary key id of the last added entry
    */
   public function lastInsertId(): ?int
   {
      return $this->last_insert_id;
   }

   public function insertData( array $data, bool $updateOnDuplicate = false ): void
   {
      /* get all non-skipped rows */
      $col_list = array_filter( $this->getColumns(), function($c) { return !$c->doSkip(); } );
      /* construct the query */
      $query = new InsertQuery();
      $query->table_spec   = $this->getName();
      $query->columns      = array_fill_keys( array_keys($col_list), '?' );
      $query->on_duplicate = $updateOnDuplicate;

      /* handle file uploads */
      $this->handleFileUploads($data);

      /* build up a row set of the to-be-added data */
      /* data is split into array-data and scalar data
       * a mix of both will (at least) occur for referenced tables,
       * where the reference to the main table will be given as scalar and
       * has to be used for each row of the referenced table.
       */
      $datasets      = []; // array of array of several rows of data
      $data_single   = []; // array of single-row-data
      $data_as_array = false; // check whether it's array data at all
      foreach( $col_list as $colname => $col ) /**@var PlainColumn $col */
      {
         $field_name = $col->getAfixedName();
         if( is_array($data[$field_name]) )
         {
            if( ($col->getType() === 'Boolean') && get_class($col) === PlainColumn::class )
            {
               /* special handling for boolean (handled via checkboxes in PlainColumn)
                * data fields contain the row numbers which are to be set
                */
               for( $i = 0; $i < count($datasets); ++$i )
               {
                  $datasets[$i][$colname] = 0;
               }

               for( $i = 0; $i < count($data[$field_name]); ++$i )
               {
                  $datasets[ $data[$field_name][$i]-1 ][$colname] = 1;
               }
            }
            else
            {
               /* copy the input data into the corresponding row of $refdata */
               for( $i = 0; $i < count($data[$field_name]); ++$i )
               {
                  $datasets[$i][$colname] = $data[$field_name][$i]??$col->getDefault();
               }
            }

            $data_as_array = true;
         }
         else if( isset($data[$field_name]) )
         {
            $data_single[$colname] = $data[$field_name]??$col->getDefault();
         }
         else
         {
            /* missing in input */
         }
      }

      /* hard-setting of filter */
      foreach( $this->filter as $colname => $val )
      {
         $query->columns[$colname] = '?';
         $col_list[$colname]       = null;
         $data_single[$colname]    = $val;
      }

      /* catch 'non-array-input' case */
      if( !$data_as_array )
      {
         $datasets[] = $data_single;
      }

      /* prepare the update statement */
      $stmt = $this->m_dbh->prepare( $query->asString() );

      /* store the data into the database */
      foreach( $datasets as $row )
      {
         /* construct query input array */
         $dataset = [];
         foreach( $col_list as $colname => $col )
         {
            $dataset[] = $row[$colname]??$data_single[$colname]??$col->getDefault();
         }

         /* store this row */
         if( !$stmt->execute($dataset) ) throw new DatabaseUpdateError( $stmt->errorInfo()[2] );

         /* update the id col in the orginal data */
         $idcol = $this->getIdColumn();
         if( isset($idcol) && !isset($row[$idcol->getName()]) )
         {
            $field_name = $idcol->getAfixedName();
            $this->last_insert_id = $this->m_dbh->lastInsertId();

            if( is_array($data[$field_name]) ) $data[$field_name][] = $this->last_insert_id;
            else                               $data[$field_name]   = $this->last_insert_id;
         }
      }

      $this->updateReferencedTables($data);
   }

   /**
    * update an existing row in the table
    */
   public function updateRow( array $data, mixed $identifier ): void
   {
      $idcols = [];
      /* pre-process identifier */
      if( is_scalar($identifier) )
      {
         $idcols[]   = $this->getColumn( $this->getPrimaryKeyWithCheck() );
         $identifier = [ $idcols[0]->getName() => $identifier ];
      }
      else
      {
         foreach( array_keys($identifier) as $key )
         {
            $idcol = $this->getColumn($key);
            if( isset($idcol) )
            {
               $idcols[] = $idcol;
            }
            else
            {
               throw new \LogicException( "unknown identifier column $key in table ".$this->getName() );
            }
         }
      }

      /* get all non-skipped columns */
      $col_list = array_filter( $this->getNonKeyColumns(), function($c) { return !$c->doSkip(); } );
      /* create the update query */
      $query = new UpdateQuery();
      $query->table_spec = $this->getName();
      $query->columns    = array_fill_keys( array_keys($col_list), '?' );
      $query->filter     = array_merge(
            array_fill_keys( array_keys($identifier), '?'),
            array_fill_keys( array_keys($this->filter), '?') );


      /* collect the column name order */
      $col_list = array_merge( array_values($col_list), $idcols );

      /* handle file uploads */
      $this->handleFileUploads($data);

      /* store the data */
      $stmt = $this->m_dbh->prepare( $query->asString() );
      /** @var PlainColumn $col */
      $args = array_map( function($col) use ($data)
                         {
                            return $this->filter[$col->getName()]??$data[$col->getAfixedName()]??null;
                         }, $col_list);

      if( !$stmt->execute( $args ) ) throw new DatabaseUpdateError( $stmt->$stmt->errorInfo()[2] );

      $this->updateReferencedTables($data);
   }

   /**
    * drop a row identified by $identifier
    * @throws \LogicException
    */
   public function dropRow(mixed $identifier): void
   {
      /**
       * preprocess the $identifier
       */
      if( is_scalar($identifier) )
      {
         $identifier = [ $this->getPrimaryKeyWithCheck() => $identifier ];
      }
      else if( is_array($identifier) && !empty($identifier) )
      {
         // take it as is
      }
      else
      {
         throw new \LogicException( "no valid filter for dropping a table row given!" );
      }

      $identifier = array_merge( $identifier, $this->filter );

      $this->dropRowset( array_keys($identifier), [$identifier]);
   }

   /**
    * drop multiple rows from a table
    * @param array[string] $id_columns name of columns used to identify the rows
    * @param array[string] $id_values  array of arrays of value of the id columns
    */
   public function dropRowset( array $id_columns, array $id_values ): void
   {
      /**
       * generate the delete query and its filter
       */
      $query = new DeleteQuery();
      $query->table_spec = $this->getName();
      $query->filter     = array_fill_keys( $id_columns, '?');

      /** prepare the statement */
      $stmt = $this->m_dbh->prepare($query->asString());

      /** get a mapping $id_columns => $id_values */
      $id_assoc = array_flip($id_columns);

      /** process all $id_values and drop the identified rows */
      foreach( $id_values as $idrow )
      {
         if( !is_array($idrow) || count($idrow)!==count($id_columns) )
         {
            throw new \LogicException( "invalid input of id_values!" );
         }

         /**
          * delete all reference rows from reference tables
          */
         foreach( $this->getExternalReferences() as $ref )
         {
            if( isset($id_assoc[$ref->refcolumn]) )
            {
               $filter = $idrow[$ref->refcolumn] ?? $idrow[$id_assoc[$ref->refcolumn]];
               $ref->table->dropRowset( [ $ref->column ], [ [ $filter ] ] );
            }
         }

         /**
          * delete any attached files
          */
         if( !empty( $this->m_filehdl) )
         {
            $fname_query = new SelectQuery();
            $fname_query->filter     = $query->filter;
            $fname_query->table_spec = $query->table_spec;
            $fname_query->columns    = array_keys( $this->m_filehdl );

            $fnames = $this->m_dbh->prepare($fname_query->asString());

            if( !$fnames->execute(array_values($idrow)) ) throw new DatabaseError( $fnames->$stmt->errorInfo()[2] );

            while( $row = $fnames->fetch( \PDo::FETCH_ASSOC) )
            {
               foreach( $this->m_filehdl as $cname => $fcol ) /**@var \dbfe\FileHandlerColumn $fcol */
               {
                  if( !empty($row[$cname]) )
                     $fcol->dropFiles( [$row[$cname]] );
               }
            }
         }

         /** perform deletion of this row */
         if( !$stmt->execute( array_values($idrow) ) ) throw new DatabaseUpdateError( $stmt->$stmt->errorInfo()[2] );
      }
   }

   /**
    *
    */
   protected function updateReferencedTables( array $data ): void
   {
      foreach( $this->getExternalReferences() as $ref )
      {
         // copy the link to the main table row to the referenced data
         $colname    = PlainColumn::afixedName( $ref->column, $ref->table->getName() );
         $refcolname = PlainColumn::afixedName( $ref->refcolumn, $this->getName() );
         $data[$colname] = $data[$refcolname];

         /* check for 1:1 references whether it is to be set at all */
         $sel_name = $ref->getSelectionName();
         if( !is_null($sel_name) && !$data[$sel_name] )
         {
            $ref->table->dropRow($data[$refcolname]);
         }
         else
         {
            /* perform the updating in the database */
            $ref->table->insertData( $data, true );
         }

         /* evaluate the 'delete' selection */
         if( $ref->isOne2Many() )
         {
            $ref->table->deleteRowsFromFv($data);
         }
      }
   }

   /*
    * delete rows from table using the "delete column" as generated by Form\Printer
    * by Table::get_form_definition
    */
   public function deleteRowsFromFv(array $data): void
   {
      $del_data       = [];
      $del_id_columns = array_keys($this->getPrimaryKey());
      foreach( $data[static::getDeleteColName($this->getName())] as $idx )
      {
         /* construct the row identifier */
         $identifier = [];
         foreach( $del_id_columns as $idcolname )
         {
            $prefixed_id_name = PlainColumn::afixedName($idcolname, $this->getName());
            if( isset($this->filter[$idcolname]) )
            {
               $identifier[$idcolname] = $this->filter[$idcolname];
            }
            else if( is_array($data[$prefixed_id_name]) && isset($data[$prefixed_id_name][$idx-1]) )
            {
               $identifier[$idcolname] = $data[$prefixed_id_name][$idx-1];
            }
            else if( is_scalar($data[$prefixed_id_name]) )
            {
               $identifier[$idcolname] = $data[$prefixed_id_name];
            }
            else
            {
               throw new \LogicException( "missing $idcolname in data for ".$this->getName() );
            }
         }
         /* delete this row */
         $del_data[] = $identifier;
      }
      $this->dropRowset( $del_id_columns, $del_data );
   }

   /**
    * handle any file uploads and update corresponding fields
    * in $data
    * @param array $data in/out
    */
   protected function handleFileUploads(array &$data): void
   {
      if( empty($this->m_filehdl) ) return;

      /* determine a row identifier if possible */
      $name_col = $this->getNameColumn();
      $rowid = isset($name_col)? $data[$name_col->getAfixedName()] : null;

      foreach ($this->m_filehdl as $fcol)
      {
         // if this column is not in $_FILES, assume it was
         // skipped on purpose in the form earlier, and skip silently
         if( isset($_FILES[$fcol->getAfixedName()]) )
            $fcol->handleUpload( $data, $rowid );
      }
   }

   /**
    * get contents of the table and all referenced tables
    * in a format compatible to \Form\Printer
    */
   public function getFormData( int|string|array $selector = [] ): array
   {
      $result = [];

      /* build query to retrieve the contents of this table */
      $query = new SelectQuery();
      $query->table_spec = $this->getName();
      /* construct column retrieval */
      $query->columns = array_map( function($c) { return $c->sqlColumnSpec(); }, $this->getColumns() );
      /* add row selection */
      if( is_array($selector) )       $query->filter = $selector;
      else if( is_scalar($selector) ) $query->filter = [ $this->getPrimaryKeyWithCheck() => $selector ];
      else throw new \LogicException( "invalid selector $selector" );
      /* add customized filter (with the selector filter having higher prio */
      $query->filter = array_merge( $this->filter, $query->filter );

      if( !empty($this->order) )
      {
         /* join any reference columns into the query, to make them accessible for ordering */
         foreach( $this->getColumns() as $col )
         {
            if( $col instanceof ReferenceColumnIf )
            {
               /**@var $col \dbfe\ReferenceColumnIf */
               $table_name = $col->getTable()->getName();
               $col_name   = $col->getName();
               $query->table_spec .= " left join $table_name using($col_name)";
            }
         }

         /* add ordering */
         $query->order = $this->order;
      }

      /* execute query */
      $data = $this->m_dbh->query( $query->asString() );
      if( !$data ) return [];
      $rowid = 1;
      while( $row = $data->fetch(\PDO::FETCH_ASSOC) )
      {
         foreach( $row as $name => $value )
         {
            $col = $this->getColumn($name);

            if( ($col->getType() === 'Boolean') && (get_class($col) === PlainColumn::class) )
            {
               if( $value ) $result[$col->getAfixedName()][] = $rowid;
            }
            else
            {
               $result[PlainColumn::afixedName($name, $this->getName())][] = $value;
            }
         }

         $rowid += 1;
      }

      /* traverse all external referencing tables and
       * get their content
       */
      foreach( $this->getExternalReferences() as $refTab )
      {
         /* the external reference column that shows on this
          * table needs to reference our ID column
          * no other setup supported (for now)
          */
         if( !isset($query->filter[$refTab->refcolumn]) )
         {
            trigger_error( sprintf("unsupported reference: %s.%s => %s.%s",
                                   $refTab->table->getName(), $refTab->column, $this->getName(), $refTab->refcolumn ),
                  E_USER_WARNING );
            continue;
         }

         /* retrieve all data connected to the current entry of this table */
         $ref_selector = [ $refTab->column => $query->filter[$refTab->refcolumn] ];
         $ref_data = $refTab->table->getFormData( $ref_selector );

         /* integrate this data into our result set */
         if( !empty($ref_data) )
         {
            $result = array_merge( $result, $ref_data );

            /* enable any selection fields for 1:1 tables */
            $sel_name = $refTab->getSelectionName();
            if( !is_null($sel_name) )
            {
               $result[$sel_name] = '1';
            }
         }
      }

      return $result;
   }


   /**
    * {@inheritDoc}
    * @see \getoma\dbfe\Table\TableIf::getFormDefinition()
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data, array $options = []): ConfigurationListIf
   {
      /* _.defaults for $options... */
      foreach( [ 'groups' => [], 'as_array' => false, 'skip' => [], 'required_only' => false ] as $opt => $default )
      {
         if( !isset($options[$opt]) ) $options[$opt] = $default;
      }

      /* create a (column => group) mapping for easy access during the iteration */
      $grouping = [];
      foreach( $options['groups'] as $group => $fields )
      {
         $grouping = array_merge( $grouping, array_fill_keys( $fields, $group ) );
      }
      /** @var ConfigurationIf[]  store links to any created group */
      $groups = [];

      /** generate the form configuration **/
      $result = new \getoma\dbfe\Form\Printer\Configuration\ConfigurationList();

      // generate all fields
      foreach( $this->getColumns() as $colname => $col )
      {
         // check if to skip
         if( in_array($colname, $options['skip']) )             continue;
         if( $options['required_only'] && !$col->isRequired() ) continue;
         if( $col->doSkip() )                                   continue;

         // auto_increment values are only added as hidden fields
         if( $col->isAutoIncrement() )
         {
            $result[] = [ 'type' => 'hidden', 'name' => $col->getAfixedName($options['as_array']) ];
            continue;
         }

         /* create a sublist and add the column configuration */
         $coldef_list = new \getoma\dbfe\Form\Printer\Configuration\ConfigurationList( $col->getFormDefinition( $lblHdl, $data, $options['as_array'] ) );

         /* loop through the list of columns and add them to the output */
         foreach( $coldef_list as $coldef )
         {
            $groupname = @$grouping[$colname];

            if( isset( $groupname ) )
            {
               if( !isset( $groups[$groupname] ) )
               {
                  $result[] = [
                        'type' => 'fieldset',
                        'name' => $groupname,
                        'label' => $lblHdl->get($groupname, $this->getName()),
                  ];

                  $groups[$groupname] = $result->back();
               }
               $groups[$groupname]->children()->add($coldef);
            }
            else
            {
               $result[] = $coldef;
            }
         }
      }

      if( !$options['required_only'] )
      {
         /* generate any external references */
         $sel_group = null;
         foreach( $this->getExternalReferences() as $ref_name => $ref_tab )
         {
            /* the external reference column that shows on this
             * table needs to reference our single(!) primary key column
             * no other setup supported (for now)
             */
            $pkeys = array_keys( $this->getPrimaryKey() );
            if(!( (count($pkeys)===1) && ($ref_tab->refcolumn === $pkeys[0]) ) )
            {
               throw new DatabaseError( "unsupported reference: {$ref_tab->table}.{$ref_tab->column} " .
                                        " => {$this->getName()}.{$ref_tab->refcolumn}", E_USER_WARNING );
            }

            /* generate selection field for 1:1 dependencies, right before the first such table
             * This field allows the user to enable/disable the single sub tables
             */
            $sel_name = $ref_tab->getSelectionName();
            if( !is_null($sel_name) )
            {
               if( is_null($sel_group) )
               {
                  $sel_group = new \getoma\dbfe\Form\Printer\Configuration\Configuration(
                               [ 'type' => 'fieldset', 'name' => 'table_selection',
                                 'label' => $lblHdl->get('table_selection', $this->getName()),
                                 'content' => []  ] );
                  $result[] = $sel_group;
               }

               $sel_group->children()->add( [ 'type'  => 'checkbox', 'value' => '1'
                                            , 'name'  => $sel_name
                                            , 'label' => $lblHdl->get( $ref_tab->table->getName() ) ] );
            }


            /* generate the sub form via recursive call
             * - as array if one-to-many dependency
             * - skip the reference column (as it contains only the row id of the current data set)
             */
            $ref_as_array = $ref_tab->isOne2Many()? true : $options['as_array'];
            $columns = $ref_tab->table->getFormDefinition($lblHdl, $data, [ 'as_array' => $ref_as_array, 'skip' => [$ref_tab->column] ] );

            if(  $this->fieldsets_for_references                                   // reference fieldsets enabled
              &&!array_reduce( $columns->content(), function($r,$o) { return $r && ($o['type'] === 'fieldset'); }, true ) // children are more than just other fieldsets
              )
            {
               $result[] = [ 'type'    => 'fieldset',
                             'name'    => $ref_name,
                             'label'   => $lblHdl->get($ref_name),
                             'content' => $columns                 ];
            }
            else
            {
               $result[] = [ 'type' => 'Group', 'name' => $ref_name, 'content' => $columns ];
            }
         }
      }

      /* if this is an array:
       * - add a "delete" column
       * - encapsulate into an ArrayGroup
       */
      if( $options['as_array'] )
      {
         /* determine the number of rows to prefill the "delete" values */
         $rowcount = 0;
         foreach( $this->getPrimaryKey() as $pcol )
         {
            $cname = $pcol->getAfixedName();
            if( isset($data[$cname]) )
            {
               $rowcount = count($data[$cname]);
               break;
            }
         }

         $row_range = range(1, $rowcount);

         /* special handling for checkboxes: put the row # as value for each box */
         foreach( $result as $entry )
         {
            if( $entry['type'] === 'checkbox' ) $entry['value'] = $row_range;
         }

         /* create the "del" checkbox */
         if( $rowcount )
         {
            $result[] = [ 'name' => static::getDeleteColName( $this->getName(), true ), 'label' => $lblHdl->get('delete'),
                          'type' => 'checkbox', 'value' => $row_range, 'class' => 'delete_entry' ];
         }

         /* create the surrounding array group */
         $result = new \getoma\dbfe\Form\Printer\Configuration\ConfigurationList( [ [ 'name' => $this->getName(), 'type' => 'ArrayGroup', 'content' => $result ] ] );
      }

      return $result;
   }

   /**
    * get \Form\Validator configuration for this table
    * if $skip_primary set, the primary key is not included (useful if new table
    * entries are to be added)
    * allow to provide customized constraints via dedicated parameter
    */
   public function getFormValidation( bool $optional_id = false, bool $as_array = false, $skip = [] ): \getoma\dbfe\Form\Validator\Profile
   {
      $result = new \getoma\dbfe\Form\Validator\Profile();

      foreach( $this->getColumns() as $column )
      {
         // check for explicitly skipped columns
         if( in_array( $column->getName(), $skip) ) continue;
         if( $column->doSkip() ) continue;
         // integrate validator configuration for this column
         $colval = $column->getValidatorConfig($as_array);
         // downgrade id column to optional if requested, set empty default value
         if( $optional_id && $column->isAutoIncrement() )
         {
            $colval->optional = $colval->required;
            $colval->required = [];
         }
         // merge column definition
         $result->merge( $colval );
      }

      if( $as_array )
      {
         /* for arrays, the required columns form a dependency group */
         $result->dependencies[] = $result->required;
         $result->required = [];

         /* also forsee the row delete column */
         $result->optional[] = static::getDeleteColName($this->getName(), true);
      }

      /* load validations for referenced tables */
      foreach( $this->getExternalReferences() as $extref )
      {
         $ref_as_array = $extref->isOne2Many()? true : $as_array;
         $result->merge( $extref->table->getFormValidation( false, $ref_as_array, [ $extref->refcolumn ] ) );

         /* add optional sub table selections */
         $sel_name = $extref->getSelectionName();
         if( !is_null($sel_name) )
         {
            $result->optional[] = $sel_name;
         }
      }

      return $result;
   }

   private static function getDeleteColName( string $tabname, $as_array = false ): string
   {
      return 'del' . $tabname . ($as_array? '[]' : '');
   }
}

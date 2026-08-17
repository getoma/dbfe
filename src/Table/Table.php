<?php

namespace getoma\dbfe\Table;

use getoma\dbfe\Form\Generator\Node\ArrayGroupNode;
use getoma\dbfe\Form\Generator\Node\CompositeNode;
use getoma\dbfe\Form\Generator\Node\GroupNode;
use getoma\dbfe\Form\Generator\Node\TransparentGroup;
use getoma\dbfe\Form\Generator\Field\HiddenField;
use getoma\dbfe\Form\Generator\Field\BooleanField;
use getoma\dbfe\Table\Column\FileContentType;
use getoma\dbfe\Table\Column\FileHandlerColumn;
use getoma\dbfe\Table\Column\PlainColumn;
use getoma\dbfe\Table\Column\ColumnIf;
use getoma\dbfe\Table\Column\ReferenceColumn;
use getoma\dbfe\Table\Column\ReferenceColumnIf;
use getoma\dbfe\Table\Column\SelectionColumn;
use getoma\dbfe\Util\Exception\DatabaseError;
use getoma\dbfe\Util\Exception\DatabaseUpdateError;
use getoma\dbfe\Util\FileHandler\FileHandlerIf;
use getoma\dbfe\Util\ValidatedInput;

use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\QueryFactory;
use Respect\Validation\Validator;

class Table implements TableIf
{
   /** @var ColumnIf[] */
   protected array $m_columns = [];

   /** @var ColumnIf[] */
   protected array $m_primKeys = [];

   /** @var ColumnIf[] */
   protected array $m_unique = [];

   /** @var FileHandlerColumn[] */
   protected array $m_filehdl = [];

   /** @var TableReference[] */
   protected array $m_extRef = [];

   /** @var string[] */
   protected array $order = [];

   /** @var string[] */
   protected array $filter = [];

   /** @var ?int */
   protected ?int $last_insert_id = null;

   /**
    * @param Factory $factory
    * @param \PDO    $dbh
    * @param string $name
    * @param int   $options any of BIDIRECTIONAL_REFERENCES | NO_HEURISTIC_TYPES | NO_REFERENCES
    * @throws \Exception
    */
   public function __construct(
      protected readonly Factory $factory,
      protected readonly \PDO $dbh,
      protected readonly QueryFactory $query_factory,
      protected readonly string $name,
      int $options = 0
   )
   {
      $structure = $this->dbh->query('explain ' . $name)->fetchAll();

      /* traverse through all columns of the table and process them */
      foreach( $structure as $col )
      {
         $obj = new PlainColumn( $col, $this->getName(), !($options & self::NO_HEURISTIC_TYPES) );

         $this->m_columns[$obj->getName()] = $obj;
         if( $obj->isPrimaryKey() ) $this->m_primKeys[$obj->getName()] = $obj;
         if( $obj->isUnique()     ) $this->m_unique[$obj->getName()]   = $obj;
      }

      /* set up references to other tables */
      if( !($options & self::NO_REFERENCES) )
      {
         $this->linkReferences($factory, $options);
      }

      /* TODO: do some validity checking on factory creation */
      $this->query_factory ??= new QueryFactory($dbh->getAttribute(\PDO::ATTR_DRIVER_NAME));
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
      $ref_result = $this->dbh->query($query);

      if( !$ref_result ) return;

      while( $row = $ref_result->fetch(\PDO::FETCH_ASSOC) )
      {
         $reftable = $factory->loadTable( $row["referenced_table_name"], false, $visited );

         /* avoid endless recursion by only allowing bidirectional links on non-visited tables */
         if( !in_array($reftable->getName(), $visited) && ($options & self::BIDIRECTIONAL_REFERENCES) )
         {
            $reftable->registerReference( new TableReference( $this, $row['column_name'], $row['referenced_column_name']) );
         }

         $obj = new ReferenceColumn( $this->getColumn($row['column_name']), $this->getName(), $reftable,
                                     $this->dbh, $this->query_factory );

         $this->m_columns[$obj->getName()] = $obj;
         if( $obj->isPrimaryKey() ) $this->m_primKeys[$obj->getName()] = $obj;
         if( $obj->isUnique()     ) $this->m_unique[$obj->getName()]   = $obj;
      }
   }

   /**
    * register a filehandler for a certain column
    * @param string $column
    * @param FileHandlerIf $fh
    */
   public function setFilehandler( string $column, FileHandlerIf $fh, FileContentType $content_type = FileContentType::Opaque, bool $support_delete = false ): void
   {
      if( isset( $this->m_columns[$column] ) )
      {
         $col = new FileHandlerColumn( $this->m_columns[$column], $this->getName(), $fh, $content_type, $support_delete );
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
   public function setValueSelection( string $column, array|SelectInterface $selection ): void
   {
      if( isset( $this->m_columns[$column] ) )
      {
         if( $selection instanceof SelectInterface )
         {
            $query = $this->dbh->prepare($selection->getStatement());
            $query->execute($selection->getBindValues());
            $selection = $query->fetchAll(\PDO::FETCH_COLUMN);
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
      return $this->name;
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
   public function registerReference( TableReference $ref ): void
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
    * check if a specific id existst in the table data
    */
   public function hasId( int $id ): bool
   {
      $result = false;

      $id_col = $this->getIdColumn();
      if( isset($id_col) )
      {
         $query = $this->query_factory->newSelect()
            ->cols(['count(*)'])
            ->from($this->getName())
            ->where($id_col->getName().'=?', [$id]);
         $stmt = $this->dbh->prepare($query->getStatement());
         $stmt->execute($query->getBindValues());
         $result = $stmt->fetchColumn() > 0;
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

   /**
    * insert data into the table
    */
   public function insertData(ValidatedInput $data, bool $updateOnDuplicate = false, array $reference_filter = []): void
   {
      /* get all non-skipped rows */
      $col_list = array_filter( $this->getColumns(), function($c) { return !$c->doSkip(); } );
      /* construct the query */
      $iquery = $this->query_factory->newInsert();
      $iquery->into($this->getName());
      $iquery->cols( $col_list );
      $uquery = null;
      $squery = null;
      if( $updateOnDuplicate )
      {
         /* additionally, prepare an update statement, as well as a select statement to check whether we have
          * anything to update.
          * For now, there is no other chance to know whether a data set is already part of the database
          * with standard SQL features - INSERT IGNORE, ON DUPLICATE KEY UPDATE, UPSERT, etc. are all
          * non-standardized extensions of the specific database engines.
          * As a future extension, we could add a marker to the provided forms to mark pre-existing datasets
          */
         $ucol_list = array_filter( $this->getNonKeyColumns(), fn($c) => !$c->doSkip() );
         if( $ucol_list )
         {
            /* pure relationship tables may consists only of their primary key.
             * for those tables, we cannot and don't need to prepare an update statement
             * for those, we only need to check whether we have to insert, and be done otherwise
             */
            $uquery = $this->query_factory->newUpdate()
               ->table($this->getName())
               ->cols( array_filter( array_map( fn($c) => $c->getName(), $ucol_list),
                       fn($c) => !array_key_exists($c, $reference_filter) ) );
         }

         $squery = $this->query_factory->newSelect()
         ->from($this->getName())
         ->cols(['count(*)']);

         foreach( $this->getPrimaryKey() as $c )
         {
            $filt = "{$c->getName()}=:{$c->getName()}";
            if($uquery) $uquery->where($filt);
            $squery->where($filt, [$c->getName() => null]);
         }
      }

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
      foreach( $col_list as $colname => $col ) /** @var PlainColumn $col */
      {
         $field_data = $data->get($col->getAffixedName());
         if( is_array($field_data) )
         {
            /* copy the input data into the corresponding row of $refdata */
            for( $i = 0; $i < count($field_data); ++$i )
            {
               $datasets[$i][$colname] = $field_data[$i]??$col->getDefault();
            }
         }
         else if( isset($field_data) )
         {
            $data_single[$colname] = $field_data ?? $col->getDefault();
         }
         else
         {
            /* missing in input */
         }
      }

      /* if no relevant data found, abort here */
      if( !$datasets && !$data_single) return;

      /* hard-setting of filter */
      $primKeys = $this->getPrimaryKey();
      foreach( $reference_filter + $this->filter as $colname => $val )
      {
         $iquery->cols([$colname]);
         if( $uquery && !isset($primKeys[$colname]) ) $uquery->where("$colname=:$colname");
         $col_list[$colname]    = null;
         $data_single[$colname] = $val;
      }

      /* prepare the update statement */
      $istmt = $this->dbh->prepare( $iquery->getStatement() );
      $ustmt = $uquery? $this->dbh->prepare( $uquery->getStatement() ) : null;
      $sstmt = $squery? $this->dbh->prepare( $squery->getStatement() ) : null;
      $idcol = $this->getIdColumn() ?? false;

      /**
       * if this method shall be able to handle pre-existing entries ("upsert"/"on duplicate key update")
       * following handling takes place:
       * - if this table has an AUTO_INCREMENT column ("IdColumn"), we check if the row ID is already set
       *   --> if id is set, attempt an update, otherwise attempt an insert
       * - if this table has no ID column, then
       *   --> first check if this entry already exists, before updating or inserting
       */
      /* store the data into the database */
      foreach( $datasets?:[$data_single] as $row )
      {
         /* construct query input array */
         $dataset = [];
         foreach( $col_list as $colname => $col )
         {
            $v = $row[$colname]??$data_single[$colname]??$col->getDefault();
            if( $v === '' ) $v = $col->getDefault();
            $dataset[$colname] = $v;
         }

         if( $updateOnDuplicate )
         {
            if( $idcol )
            {
               /* we have an auto_increment column - decide based on its existing value what to do */
               if( $dataset[$idcol->getName()] ?? false ) $ustmt->execute($dataset); // id already exists --> update
               else                                       $istmt->execute($dataset); // no id, yet --> insert
            }
            else
            {
               // check if row already exists, then update or insert based on that info
               $select_filter = array_intersect_key($dataset, $squery->getBindValues());
               $sstmt->execute($select_filter);
               if( $sstmt->fetchColumn() === 0 )
               {
                  $istmt->execute($dataset);
               }
               else if( $ustmt)
               {
                  $ustmt->execute($dataset);
               }
               else
               {
                  // no ustmt prepared, which means there is nothing to update
               }
            }
         }
         else
         {
            // we don't have to consider pre-existing duplicates, just insert
            $istmt->execute($dataset);
         }

         /* update the id col in the orginal data after an insert */
         if( $idcol && !$row[$idcol->getName()] )
         {
            $field_name = $idcol->getAffixedName();
            $this->last_insert_id = $this->dbh->lastInsertId();

            if( $data->is_array($field_name) ) $data->push( $field_name, $this->last_insert_id);
            else                               $data->store($field_name, $this->last_insert_id);
         }
      }

      $this->updateReferencedTables($data);
   }

   /**
    * update an existing row in the table
    */
   public function updateRow( ValidatedInput $data, mixed $identifier ): void
   {
      /* pre-process identifier */
      if( is_scalar($identifier) )
      {
         $idcol_name = $this->getPrimaryKeyWithCheck();
         $identifier = [ $idcol_name => $identifier ];
      }
      else
      {
         if( $key = array_find_key( $identifier, fn($val, $key) => !$this->getColumn($key) ) )
         {
            throw new \LogicException( "unknown identifier column $key in table " . $this->getName() );
         }
      }
      $identifier += $this->filter;

      /* handle file uploads */
      $this->handleFileUploads($data);

      /* get all non-skipped columns and their data if provided */
      $args = [];
      foreach( $this->getNonKeyColumns() as $column )
      {
         if( $column->doSkip() || !$data->has($column->getAffixedName()) ) continue;
         $args[$column->getName()] = $data->get_scalar($column->getAffixedName()) ?: $column->getDefault();
      }

      /* create the update query */
      $query = $this->query_factory->newUpdate();
      $query->table($this->getName());
      $query->cols($args);
      foreach( array_keys($identifier) as $col )
      {
         $query->where("$col=:$col");
      }

      /* store the data */
      $stmt = $this->dbh->prepare( $query->getStatement() );
      if( !$stmt->execute( $identifier + $args ) ) throw new DatabaseUpdateError( $stmt->$stmt->errorInfo()[2] );

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
      $query = $this->query_factory->newDelete();
      $query->from($this->getName());
      foreach( $id_columns as $colname ) $query->where("$colname=?");

      /** prepare the statement */
      $stmt = $this->dbh->prepare($query->getStatement());

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
            $fname_query = $this->query_factory->newSelect();
            $fname_query->from($this->getName());
            $fname_query->cols(array_keys( $this->m_filehdl ));
            foreach( $id_columns as $colname ) $fname_query->where("$colname=?");

            $fnames = $this->dbh->prepare($fname_query->getStatement());

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
   protected function updateReferencedTables(ValidatedInput $data): void
   {
      foreach( $this->getExternalReferences() as $ref )
      {
         // copy the link to the main table row to the referenced data
         $extcolname = PlainColumn::affixedName( $ref->column, $ref->table->getName() );
         $refcolname = PlainColumn::affixedName( $ref->refcolumn, $this->getName() );
         $ref_id = $data->get_scalar($refcolname);
         $ref_filter = [ $ref->column => $ref_id ];

         /* evaluate the 'delete' selection */
         if( $ref->isOne2Many() )
         {
            $ref->table->insertData( $data, true, $ref_filter );
            $ref->table->deleteRowsFromFv($data, $ref_filter);
         }
         else
         {
            /* check for 1:1 references whether it is set */
            if( $data->get_scalar($extcolname) )
            {
               /* perform the updating in the database */
               $ref->table->insertData( $data, true, $ref_filter );
            }
            else
            {
               /* make sure the referenced table is cleaned for this id */
               $ref->table->dropRow($ref_id);
            }
         }
      }
   }

   /**
    * delete rows from table using the generated delete selector column.
    */
   public function deleteRowsFromFv(ValidatedInput $data, array $reference_filter = []): void
   {
      $del_data       = [];
      $del_id_columns = array_keys($this->getPrimaryKey());
      foreach( $data->get_array($this->getDeleteColName()) as $idx => $do_del )
      {
         if( !$do_del ) continue;

         /* construct the row identifier */
         $identifier = [];
         foreach( $del_id_columns as $idcolname )
         {
            $id_data = $data->get(PlainColumn::affixedName($idcolname, $this->getName()));
            if( isset($this->filter[$idcolname]) )
            {
               $identifier[$idcolname] = $this->filter[$idcolname];
            }
            else if( isset($reference_filter[$idcolname]) )
            {
               $identifier[$idcolname] = $reference_filter[$idcolname];
            }
            else if( is_array($id_data) && isset($id_data[$idx]) )
            {
               $identifier[$idcolname] = $id_data[$idx];
            }
            else if( is_scalar($id_data) )
            {
               $identifier[$idcolname] = $id_data;
            }
            else
            {
               throw new \LogicException( "missing $idcolname in data for ".$this->getName() );
            }
         }
         /* delete this row */
         $del_data[] = $identifier;
      }
      if( $del_data ) $this->dropRowset( $del_id_columns, $del_data );
   }

   /**
    * handle any file uploads and update corresponding fields
    * in $data
    * @param ValidatedInput $data in/out
    */
   protected function handleFileUploads(ValidatedInput $data): void
   {
      if( empty($this->m_filehdl) ) return;

      /* determine a row identifier if possible */
      $name_col = $this->getNameColumn();
      $rowid = isset($name_col)? $data->get($name_col->getAffixedName()) : null;

      foreach ($this->m_filehdl as $fcol)
      {
         // if this column is not in $_FILES, assume it was
         // skipped on purpose in the form earlier, and skip silently
         if( isset($_FILES[$fcol->getAffixedName()]) )
            $fcol->handleUpload( $data, $rowid );
      }
   }

   /**
    * get contents of the table and all referenced tables
    */
   public function getFormData(int|string|array $selector = [], bool $as_array = false): array
   {
      $result = [];

      /* build query to retrieve the contents of this table */
      $query = $this->query_factory->newSelect();
      $query->from($this->getName());
      /* construct column retrieval */
      $query->cols(array_values(array_map( fn($c) => $c->sqlColumnSpec(), $this->getColumns() )) );
      /* add row selection */
      if( is_scalar($selector) )
      {
         $selector = [ $this->getPrimaryKeyWithCheck() => $selector ];
      }
      if( !is_array($selector) ) throw new \LogicException( "invalid selector of type " . get_class($selector));
      /* enforce array output if the selector does contain all primary key columns */
      $as_array = $as_array || array_diff(array_keys($this->getPrimaryKey()), array_keys($selector));

      /* add customized filter (with the selector filter having higher prio) and incorporate into query */
      foreach( array_merge($this->filter, $selector) as $col => $value )
      {
         $query->where("$col=:$col", [$col => $value]);
      }

      if( !empty($this->order) )
      {
         /* join any reference columns into the query, to make them accessible for ordering */
         foreach( $this->getColumns() as $col )
         {
            if( $col instanceof ReferenceColumnIf )
            {
               $table_name = $col->getTable()->getName();
               $col_name   = $col->getName();
               $query->join('left', $table_name, "using ($col_name)");
            }
         }

         /* add ordering */
         $query->orderBy($this->order);
      }

      /* execute query */
      $stmt = $this->dbh->prepare( $query->getStatement() );
      $filter = $query->getBindValues();
      $stmt->execute($filter);
      $rowid = 1;
      while( $row = $stmt->fetch(\PDO::FETCH_ASSOC) )
      {
         if( !$as_array && $rowid > 1 ) throw new \LogicException("received multiple datasets for non-array request");
         foreach( $row as $name => $value )
         {
            $col = $this->getColumn($name);

            if( $as_array )
            {
               $result[ $col->getAffixedName() ][] = $value;
            }
            else
            {
               $result[ $col->getAffixedName() ] = $value;
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
         if( !isset($filter[$refTab->refcolumn]) )
         {
            trigger_error( sprintf("unsupported reference: %s.%s => %s.%s",
                                   $refTab->table->getName(), $refTab->column, $this->getName(), $refTab->refcolumn ),
                  E_USER_WARNING );
            continue;
         }

         /* retrieve all data connected to the current entry of this table */
         $ref_as_array = $as_array || $refTab->isOne2Many();
         $ref_selector = [ $refTab->column => $filter[$refTab->refcolumn] ];
         $result += $refTab->table->getFormData( $ref_selector, $ref_as_array );
         $refval_key = PlainColumn::affixedName($refTab->column, $refTab->table->getName());

         if( !$ref_as_array && ($result[$refval_key]??false) )
         {
            /* for 1:1 reference, modify the reference column value to a true boolean
             * as expected by the checkbox field we are adding at form generation
             */
            $result[$refval_key] = 1;
         }
      }

      return $result;
   }

   public function getFormGeneratorDefinition(
      bool $as_array = false,
      bool $required_only = false,
      array $groups = [],
      ?string $refcol = null,
   ): CompositeNode
   {
      $grouping = [];
      foreach( $groups as $group => $fields )
      {
         $grouping = array_merge( $grouping, array_fill_keys( $fields, $group ) );
      }

      /** @var array<string, GroupNode> $groups */
      $groups = [];
      $result = $as_array
         ? new ArrayGroupNode($this->getName(), delete_name: $this->getDeleteColName())
         : new GroupNode($this->getName());

      foreach( $this->getColumns() as $colname => $col )
      {
         if( $required_only && !$col->isRequired() ) continue;
         if( $col->doSkip() )                        continue;

         if( $colname === $refcol )
         {
            /* if printed as array, the reference column value is fully redundant
             * in the ui mask part of this child table, so skip it.
             */
            if( $as_array ) continue;

            /* for the non-array case, this is a 1:1 reference, and we can use
             * it to add a dedicated checkbox to set/reset this reference
             */
            $result->setSelector(new BooleanField($col->getAffixedName()));
            continue;
         }

         if( $col->isAutoIncrement() )
         {
            $result->add(new HiddenField($col->getAffixedName()));
            continue;
         }

         $node = $col->getFormGeneratorDefinition();

         if( $groupname = $grouping[$colname] ?? null )
         {
            if( !isset( $groups[$groupname] ) )
            {
               $groups[$groupname] = new GroupNode($groupname);
               $result->add($groups[$groupname]);
            }
            $groups[$groupname]->add($node);
         }
         else if( $node instanceof TransparentGroup )
         {
            foreach( $node->children() as $child )
            {
               $result->add($child);
            }
         }
         else
         {
            $result->add($node);
         }
      }

      if( !$required_only )
      {
         foreach( $this->getExternalReferences() as $ref_tab )
         {
            $pkeys = array_keys( $this->getPrimaryKey() );
            if(!( (count($pkeys)===1) && ($ref_tab->refcolumn === $pkeys[0]) ) )
            {
               throw new DatabaseError( "unsupported reference: {$ref_tab->table}.{$ref_tab->column} " .
                                        " => {$this->getName()}.{$ref_tab->refcolumn}", E_USER_WARNING );
            }

            $ref_as_array = $as_array || $ref_tab->isOne2Many();
            $child_mask   = $ref_tab->table->getFormGeneratorDefinition($ref_as_array, refcol: $ref_tab->column);
            $result->add($child_mask);
         }
      }

      return $result;
   }

   /**
    * get validator configuration for this table
    * if $skip_primary set, the primary key is not included (useful if new table
    * entries are to be added)
    * allow to provide customized constraints via dedicated parameter
    */
   public function getFormValidation( bool $optional_id = false, bool $as_array = false, $skip = [] ): array
   {
      $result = [];

      $content_columns = [];  // memorize all content columns for array input validation
      $required_columns = []; // memorize all required columns for array input validation
      foreach( $this->getColumns() as $column )
      {
         // check for explicitly skipped columns
         if( in_array( $column->getName(), $skip) ) continue;
         if( $column->doSkip() ) continue;
         // integrate validator configuration for this column
         $col_name = $column->getAffixedName();
         $result += [ $col_name => $column->getValidatorConfig($as_array, $optional_id && $column->isAutoIncrement()) ];
         // store back this name for "required array entries" check
         if( $as_array && !$column->isAutoIncrement() )
         {
            $content_columns[] = $col_name;
            if( $column->isRequired() ) $required_columns[] = $col_name;
         }
      }

      foreach( $required_columns as $validated )
      {
         /* add a validator that mandates the same number of entries for each required array entry */
         $result[$validated] = Validator::allOf(
            $result[$validated],
            Validator::callback(
               static fn(array $input) => !self::findMissingMandatoryValues($input, $content_columns, [$validated], true)
            )->setTemplate('Column need to be set for each row.')
         )
         ->setName($validated);
      }

      /* load validations for referenced tables */
      foreach( $this->getExternalReferences() as $extref )
      {
         $ref_as_array = $as_array || $extref->isOne2Many();
         $result += $extref->table->getFormValidation( false, $ref_as_array, [ $extref->refcolumn ] );
      }

      return $result;
   }

   protected function getDeleteColName(): string
   {
      return PlainColumn::affixedName('delete_row', $this->getName());
   }

   /**
    * @return list<array{field: string, index: int}>
    */
   private static function findMissingMandatoryValues(array $input, array $knownFields, array $mandatoryFields, bool $failQuick = false): array
   {
      $isEmpty = static fn(mixed $value): bool =>
         $value === null || (is_string($value) && trim($value) === '');

      $lastUsedIndex = null;

      foreach ($knownFields as $field)
      {
         $values = $input[$field] ?? [];

         if (!is_array($values)) throw new \LogicException("input $field is not an array");

         foreach ($values as $index => $value)
         {
            if (!$isEmpty($value))
            {
               $lastUsedIndex = max($lastUsedIndex??0, (int)$index);
            }
         }
      }

      if ($lastUsedIndex === null)
      {
         return [];
      }

      $missing = [];

      for ($index = 0; $index <= $lastUsedIndex; ++$index)
      {
         foreach ($mandatoryFields as $field)
         {
            $values = $input[$field] ?? [];

            if ( $isEmpty($values[$index]??null) )
            {
               $missing[] = [ 'field' => $field, 'index' => $index ];
               if( $failQuick ) return $missing;
            }
         }
      }

      return $missing;
   }
}

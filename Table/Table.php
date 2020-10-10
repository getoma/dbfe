<?php namespace dbfe;

require_once 'dbfe/Table/Column.php';
require_once 'dbfe/Util/FileHandler.php';
require_once 'dbfe/Util/Query.php';
require_once 'dbfe/Util/Exception.php';
require_once 'dbfe/Form/Validator/Profile.php';
require_once 'dbfe/Form/Printer/Configuration.php';

interface TableIf
{
   public const BIDIRECTIONAL_REFERENCES = 0x1;
   public const NO_HEURISTIC_TYPES = 0x2;
   public const NO_REFERENCES      = 0x4;

   /**
    * explicitly link references to tables references via foreign keys
    * @param Factory $factory factory to load tables that are referenced
    * @param number $options (BIDIRECTIONAL_REFERENCES)
    */
   function linkReferences( Factory $factory, $options = 0 );

   /**
    * register a filehandler for a certain column
    * @param string $column
    * @param FileHandlerIf $fh
    */
   function setFilehandler( string $column, FileHandlerIf $fh, int $display_type = DispType::link, bool $support_delete = false );

   /**
    * set a application-defined set of allowed input values
    * for a column
    * @param string $column
    * @param (array|SelectQuery) $selection
    */
   function setValueSelection( string $column, $selection );

   /**
    * get name of the table
    * @return string
    */
   function getName();

   /**
    * get auto_increment column (if any).
    * returns null if none
    *
    * @return PlainColumn
    */
   function getIdColumn();

   /**
    * return an array of the primary key column names
    * @return \dbfe\PlainColumn[]
    */
   function getPrimaryKey();

    /**
     * get name of main "name" column.
     * Basically this would be the "human readable" id of each row (in lieu of
     * the primary key id).
     * if no name column available, throw
     * @return PlainColumn
     */
   function getNameColumn();

    /**
     * get column of specific name
     * @return PlainColumn
     */
    function getColumn(string $name);

    /**
     * get all columns
     * @return PlainColumn[]
     */
    function getColumns();

    /**
     * get all columns excpept auto_increment columns
     * @return PlainColumn[]
     */
    function getNonIdColumns();

    /**
     * get all columns that are not part of primary key
     * @return PlainColumn[]
     */
    function getNonKeyColumns();

    /**
     * get number of columns
     * @return int
     */
    function getColumnCount();

    /**
     * @return TableReference[]
     */
    function getExternalReferences();

    /**
     * @return bool
     */
    function hasExternalReferences();

    /**
     * register a reference to another table
     * @param TableReference $ref
     */
    function registerReference( TableReference $ref );

    /**
     * configure the output ordering of rows for this table
     */
    function setOrdering( $order );

    /**
     * @return bool
     */
    function hasUploads();

    /**
     * retrieve data from this table
     * @param SelectQuery
     * @return \PDOStatement
     */
    function query( SelectQuery $query );

    /**
     * check if a specific id existst in the table data
     * @param int $id
     */
    function hasId( int $id );

    /**
     * get the primary key id of the last added entry
     * @return string
     */
    function lastInsertId();

    /**
     * insert data into the table
     * @param array $data
     * @param bool  $updateOnDuplicate
     */
    function insertData( array $data, bool $updateOnDuplicate = false );

    /**
     * update an existing row in the table
     * @param array $data
     * @param mixed $identifier
     * @return boolean
     */
    function updateRow( array $data, $identifier );

    /**
     * drop a row identified by $identifier
     * @param mixed $identifier
     * @throws \LogicException
     * @return \mysqli_result|boolean
     */
    function dropRow($identifier);

    /**
     * drop multiple rows from a table
     * @param array[string] $id_columns name of columns used to identify the rows
     * @param array[string] $id_values  array of arrays of value of the id columns
     * @return boolean
     */
    function dropRowset( array $id_columns, array $id_values );

    /**
     * delete rows from table using the "delete column" as generated
     * by Table::get_form_definition
     */
    function deleteRowsFromFv(array $data);

    /**
     * get contents of the table and all referenced tables
     * in a format compatible to \Form\Printer
     * @param mixed $selector
     * @param bool  $order
     */
    function getFormData( $selector = [] );

    /**
     * get a form specification that can be used as input to Form\Printer
     * @param $lblHdl LabelHandlerIf            translator interface to derive field names etc
     * @param $data array                       data that shall be put into the form [ <column> => [...data] ]
     * @param $options array                    optional configurations, see below
     *
     * options:
     *  as_array      => encapsulate whole table form into ArrayGroup
     *  skip          => list of columns to skip
     *  required_only => skip all columns but the required ones, don't print referenced forms
     *  groups        => group columns into fieldsets: [ <fieldsetname> => [ ...<column> ] ]
     *
     * @return Form\Printer\ConfigurationListIf
     */
    function getFormDefinition(LabelHandlerIf $lblHdl, array $data, array $options = []);

    /**
     * get \Form\Validator configuration for this table
     * if $skip_primary set, the primary key is not included (useful if new table entries are to be added)
     * @param $constraints  array
     * @param $skip_primary bool
     * @return Form\Validator\Profile
     */
    function getFormValidation( bool $skip_auto_increment = false, bool $as_array = false, $skip = [] );

    /**
     * whether referenced tables shall be encapsulated into <fieldset> at form output
     * @param bool $status
     * @return bool
     */
    function useFieldsetsForReferences( bool $status = null );
}

class Table implements TableIf
{
   /** @var string */
   protected $m_name;

   /** @var array[ColumnIf] */
   protected $m_columns = [];

   /** @var array[ColumnIf] */
   protected $m_primKeys = [];

   /** @var array[ColumnIf] */
   protected $m_unique = [];

   /** @var array[FileHandlerColumn] */
   protected $m_filehdl = [];

   /** @var \PDO */
   protected $m_dbh;

   /** @var array[TableReference] */
   protected $m_extRef = [];

   /** @var array[string] */
   protected $order = [];

   /** @var array[string] */
   protected $filter = [];

   /** @var bool */
   protected $fieldsets_for_references = false;

   /** @var int */
   protected $last_insert_id = null;

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
   public function linkReferences( Factory $factory, $options = 0, array $visited = [] )
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
   public function useFieldsetsForReferences( bool $status = null )
   {
      if(isset($status)) $this->fieldsets_for_references = $status;
      return $this->fieldsets_for_references;
   }

   /**
    * register a filehandler for a certain column
    * @param string $column
    * @param FileHandlerIf $fh
    */
   public function setFilehandler( string $column, FileHandlerIf $fh, int $display_type = DispType::link, bool $support_delete = false )
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
    * @param string $column
    * @param (array|SelectQuery) $selection
    */
   public function setValueSelection( string $column, $selection )
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
   public function getName()
   {
      return $this->m_name;
   }

   /**
    * get auto_increment column (if any).
    * returns null if none
    *
    * @return PlainColumn
    */
   public function getIdColumn()
   {
      foreach( $this->getColumns() as $col )
      {
         if( $col->isAutoIncrement() ) return $col;
      }
      return null;
   }

   /**
    * return an array of the primary key column names
    * @return \dbfe\PlainColumn[]
    */
   public function getPrimaryKey()
   {
      return $this->m_primKeys;
   }

   /**
    * return the name of the primary key column if there is a single one
    * throws exception, else
    * @return string
    */
   protected function getPrimaryKeyWithCheck()
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
    * if also no single primary key, throw
    * @return PlainColumn
    */
   public function getNameColumn()
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
         throw new DatabaseError("no 'name' column for table ".$this->getName());
      }
   }

   /**
    * get column of specific name
    *
    * @return PlainColumn
    */
   public function getColumn(string $name)
   {
      return $this->m_columns[$name] ?? null;
   }

   /**
    * get all columns
    *
    * @return ColumnIf[]
    */
   public function getColumns()
   {
      return $this->m_columns;
   }

   /**
    * get number of columns
    * @return int
    */
   public function getColumnCount()
   {
      return count($this->m_columns);
   }

   /**
    * @return TableReference[]
    */
   public function getExternalReferences()
   {
      return $this->m_extRef;
   }

   /**
    * @return bool
    */
   public function hasExternalReferences()
   {
      return !empty($this->m_extRef);
   }

   /**
    * get all columns EXCEPT any auto_increment column
    * @return array[PlainColumn]
    */
   public function getNonIdColumns()
   {
      return array_filter( $this->getColumns(), function($c) { return !$c->isAutoIncrement(); } );
   }

   /**
    * get all columns EXCEPT primary key columns
    * @return array[PlainColumn]
    */
   public function getNonKeyColumns()
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
   public function setOrdering( $order )
   {
      if( is_string($order) ) $order = [ $order ];
      $this->order = $order;
   }

   public function hasUploads()
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
    * @param SelectQuery
    * @return \PDOStatement
    */
   public function query( SelectQuery $query )
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
    * @param int $id
    */
   public function hasId( int $id )
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
    * @return string
    */
   public function lastInsertId()
   {
      return $this->last_insert_id;
   }

   public function insertData( array $data, bool $updateOnDuplicate = false )
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
            if( $col->getType() === 'Boolean' )
            {
               /* special handling for boolean (handled via checkboxes)
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
    *
    * @param array $data
    * @param mixed $identifier
    * @return boolean
    */
   public function updateRow( array $data, $identifier )
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
      /** @var $col PlainColumn */
      $args = array_map( function($col) use ($data)
                         {
                            return $this->filter[$col->getName()]??$data[$col->getAfixedName()]??null;
                         }, $col_list);

      if( !$stmt->execute( $args ) ) throw new DatabaseUpdateError( $stmt->$stmt->errorInfo()[2] );

      $this->updateReferencedTables($data);
   }

   /**
    * drop a row identified by $identifier
    *
    * @param mixed $identifier
    * @throws \LogicException
    */
   public function dropRow($identifier)
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
    * @return boolean
    */
   public function dropRowset( array $id_columns, array $id_values )
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
    * @param array $data       the input data
    * @param mixed $identifier of the current column in the main table
    */
   protected function updateReferencedTables( array $data )
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
   public function deleteRowsFromFv(array $data)
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
   protected function handleFileUploads(array &$data)
   {
      if( empty($this->m_filehdl) ) return;

      /* determine a row identifier if possible */
      $id_col = $this->getIdColumn();
      $rowid = isset($id_col)? $data[$id_col->getAfixedName()] : null;

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
    * @param mixed $selector
    * @param bool  $order
    */
   public function getFormData( $selector = [] )
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

            if( $col->getType() === 'Boolean' )
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
    * @see \dbfe\TableIf::getFormDefinition()
    * @return Form\Printer\ConfigurationListIf
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data, array $options = [])
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
      /** @var $groups array[\dbfe\Form\Printer\ConfigurationIf]  store links to any created group */
      $groups = [];

      /** generate the form configuration **/
      $result = new Form\Printer\ConfigurationList();

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
         $coldef_list = new Form\Printer\ConfigurationList( $col->getFormDefinition( $lblHdl, $data, $options['as_array'] ) );

         /* loop through the list of columns and add them to the output */
         foreach( $coldef_list as $coldef )
         {
            /* disable primary key columns, otherwise the mapping of input data
             * may become very problematic
             */
            if( $col->isPrimaryKey() ) $coldef['fixed'] = true;

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
               throw new DatabaseError( "unsupported reference: $ref_tab->table.$ref_tab->column " .
                                        " => $this->getName().$ref_tab->refcolumn", E_USER_WARNING );
            }

            /* generate selection field for 1:1 dependencies, right before the first such table
             * This field allows the user to enable/disable the single sub tables
             */
            $sel_name = $ref_tab->getSelectionName();
            if( !is_null($sel_name) )
            {
               if( is_null($sel_group) )
               {
                  $sel_group = new Form\Printer\Configuration(
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
               $result[] = [ 'type' => 'div', 'name' => $ref_name, 'content' => $columns ];
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
                          'type' => 'checkbox', 'value' => $row_range ];
         }

         /* create the surrounding array group */
         $result = new Form\Printer\ConfigurationList( [ [ 'name' => $this->getName(), 'type' => 'ArrayGroup', 'content' => $result ] ] );
      }

      return $result;
   }

   /**
    * get \Form\Validator configuration for this table
    * if $skip_primary set, the primary key is not included (useful if new table
    * entries are to be added)
    * allow to provide customized constraints via dedicated parameter
    *
    * @param $constraints  array
    * @param $skip_primary bool
    *
    * @return Form\Validator\Profile
    */
   public function getFormValidation( bool $optional_id = false, bool $as_array = false, $skip = [] )
   {
      $result = new Form\Validator\Profile();

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

   private static function getDeleteColName( $tabname, $as_array = false )
   {
      return 'del' . $tabname . ($as_array? '[]' : '');
   }
}

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

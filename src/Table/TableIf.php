<?php

namespace getoma\dbfe\Table;

use getoma\dbfe\Form\Printer\Configuration\ConfigurationListIf;
use getoma\dbfe\Table\Column\DispType;
use getoma\dbfe\Util\FileHandler\FileHandlerIf;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use getoma\dbfe\Util\QueryBuilder\SelectQuery;

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
     * @return ConfigurationListIf
     */
    function getFormDefinition(LabelHandlerIf $lblHdl, array $data, array $options = []): ConfigurationListIf;

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
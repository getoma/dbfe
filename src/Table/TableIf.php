<?php

namespace getoma\dbfe\Table;

use getoma\dbfe\Form\Generator\Node\CompositeNode;
use getoma\dbfe\Table\Column\ColumnIf;
use getoma\dbfe\Table\Column\FileContentType;
use getoma\dbfe\Util\FileHandler\FileHandlerIf;

use Aura\SqlQuery\Common\SelectInterface;
use getoma\dbfe\Util\ValidatedInput;

interface TableIf
{
   public const BIDIRECTIONAL_REFERENCES = 0x1;
   public const NO_HEURISTIC_TYPES = 0x2;
   public const NO_REFERENCES      = 0x4;

   /**
    * explicitly link references to tables references via foreign keys
    * @param Factory $factory factory to load tables that are referenced
    * @param int $options (BIDIRECTIONAL_REFERENCES)
    */
   function linkReferences( Factory $factory, int $options = 0 ): void;

   /**
    * register a filehandler for a certain column
    */
   function setFilehandler( string $column, FileHandlerIf $fh, FileContentType $content_type = FileContentType::Opaque, bool $support_delete = false ): void;

   /**
    * set a application-defined set of allowed input values
    * for a column
    */
   function setValueSelection( string $column, array|SelectInterface $selection ): void;

   /**
    * get name of the table
    */
   function getName(): string;

   /**
    * get auto_increment column (if any).
    * returns null if none
    */
   function getIdColumn(): ?ColumnIf;

   /**
    * return an array of the primary key column names
    * @return ColumnIf[]
    */
   function getPrimaryKey(): array;

    /**
     * get the "name" column.
     * Basically this would be the "human readable" id of each row (in lieu of
     * the primary key id).
     * if no name column available, throw
     */
   function getNameColumn(): ?ColumnIf;

    /**
     * get column of specific name
     */
    function getColumn(string $name): ColumnIf;

    /**
     * get all columns
     * @return ColumnIf[]
     */
    function getColumns(): array;

    /**
     * get all columns excpept auto_increment columns
     * @return ColumnIf[]
     */
    function getNonIdColumns(): array;

    /**
     * get all columns that are not part of primary key
     * @return ColumnIf[]
     */
    function getNonKeyColumns(): array;

    /**
     * get number of columns
     */
    function getColumnCount(): int;

    /**
     * @return TableReference[]
     */
    function getExternalReferences(): array;

    /**
     * @return bool
     */
    function hasExternalReferences(): bool;

    /**
     * register a reference to another table
     * @param TableReference $ref
     */
    function registerReference( TableReference $ref );

    /**
     * configure the output ordering of rows for this table
     * @param string|array $order - list of order by clauses
     */
    function setOrdering( string|array $order ): void;

    /**
     * @return bool
     */
    function hasUploads(): bool;

    /**
     * check if a specific id existst in the table data
     * @param int $id
     */
    function hasId( int $id ): bool;

    /**
     * get the primary key id of the last added entry
     */
    function lastInsertId(): ?int;

    /**
     * insert data into the table
     */
    function insertData(ValidatedInput $data, bool $updateOnDuplicate = false, array $reference_filter = []): void;

    /**
     * update an existing row in the table
     */
    function updateRow(ValidatedInput $data, mixed $identifier ): void;

    /**
     * drop a row identified by $identifier
     * @throws \LogicException
     */
    function dropRow(mixed $identifier): void;

    /**
     * drop multiple rows from a table
     * @param array[string] $id_columns name of columns used to identify the rows
     * @param array[string] $id_values  array of arrays of value of the id columns
     */
    function dropRowset( array $id_columns, array $id_values ): void;

    /**
     * delete rows from table using the generated delete selector column
     */
    function deleteRowsFromFv(ValidatedInput $data, array $reference_filter = []): void;

    /**
     * get contents of the table and all referenced tables
     * in a format compatible to form generator inference
     */
    function getFormData(int|string|array $selector = [], bool $as_array = false): array;

    /**
     * get a form specification as generator nodes
     */
    function getFormGeneratorDefinition(
       bool $as_array = false,
       bool $required_only = false,
       array $groups = [],
       ?string $refcol = null,
    ): CompositeNode;

    /**
     * get \Form\Validator configuration for this table
     * if $skip_primary set, the primary key is not included (useful if new table entries are to be added)
     */
    function getFormValidation( bool $skip_auto_increment = false, bool $as_array = false, $skip = [] ): array;
}
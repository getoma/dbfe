<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Printer\Configuration\ConfigurationIf;
use getoma\dbfe\Form\Printer\Configuration\ConfigurationListIf;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;
use Respect\Validation\Validator as Validator;

interface ColumnIf
{
   /**
    * add properties to the html output for this column
    * @param array $prop
    */
   public function addFormProperties(array $prop): void;

   /**
    * get a form specification that can be used as input to Form\Printer
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false): ConfigurationIf|ConfigurationListIf;

   /**
    * get the column name
    */
   public function getName(): string;

   /**
    * get the type of the column
    * @return string
    */
   public function getType(): string;

   /**
    * get or set column specifier to use in "select" query
    * get current column specifier
    * @param string $spec
    * @return string
    */
   public function sqlColumnSpec(?string $spec = null): string;

   /**
    * get the default value of the column
    */
   public function getDefault(): mixed;

   /**
    * whether this field needs to be filled with a value when writing to the DB
    * It is required if the column is "not null" AND there is no default value
    */
   public function isRequired(): bool;

   /**
    */
   public function isAutoIncrement(): bool;

   /**
    */
   public function isPrimaryKey(): bool;

   /**
    */
   public function isUnique(): bool;

   /**
    */
   public function isFixed(): bool;

   /**
    * @return string
    */
   public function getAfixedName( bool $as_array = false ): string;

   /**
    * provide column specific form\validator configuration
    */
   public function getValidatorConfig(bool $as_array = false, bool $optional = false): Validator;

   /**
    * whether this column shall be skipped in the processing
    * return current status of skipping
    */
   public function doSkip(?bool $status = null): bool;

   /**
    * add a custom constraint from the application
    */
   public function setCustomConstraint( Validator $constraint ): void;

   /**
    * make a column required although the database itself allows NULL values
    */
   public function makeRequired(): void;

   /**
    * make a column "fixed" - it cannot be modified anymore once initially set
    */
   public function makeFixed(): void;
}

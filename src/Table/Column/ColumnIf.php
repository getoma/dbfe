<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Form\Validator\Constraint\Constraint;
use getoma\dbfe\Util\LabelHandler\LabelHandlerIf;

interface ColumnIf
{
   /**
    * add properties to the html output for this column
    * @param array $prop
    */
   public function addFormProperties(array $prop);

   /**
    * get a form specification that can be used as input to Form\Printer
    * @return \dbfe\Form\Printer\Configuration|\dbfe\Form\Printer\ConfigurationList
    */
   public function getFormDefinition(LabelHandlerIf $lblHdl, array $data = [], bool $as_array = false);

   /**
    * get the column name
    * @return string
    */
   public function getName();

   /**
    * get the type of the column
    * @return string
    */
   public function getType();

   /**
    * get or set column specifier to use in "select" query
    * get current column specifier
    * @param string $spec
    * @return string
    */
   public function sqlColumnSpec($spec = null);

   /**
    * get the default value of the column
    * @return mixed
    */
   public function getDefault();

   /**
    * whether this field needs to be filled with a value when writing to the DB
    * It is required if the column is "not null" AND there is no default value
    * @return boolean
    */
   public function isRequired();

   /**
    * @return boolean
    */
   public function isAutoIncrement();

   /**
    * @return boolean
    */
   public function isPrimaryKey();

   /**
    * @return boolean
    */
   public function isUnique();

   /**
    * @return boolean
    */
   public function isFixed();

   /**
    * @return string
    */
   public function getAfixedName( bool $as_array = false );

   /**
    * provide column specific form\validator configuration
    * @return Form\Validator\Profile
    */
   public function getValidatorConfig(bool $as_array = false);

   /**
    * whether this column shall be skipped in the processing
    * return current status of skipping
    * @return bool
    */
   public function doSkip(?bool $status = null);

   /**
    * add a custom constraint from the application
    */
   public function setCustomConstraint( Constraint $constraint );

   /**
    * make a column required although the database itself allows NULL values
    */
   public function makeRequired();

   /**
    * make a column "fixed" - it cannot be modified anymore once initially set
    */
   public function makeFixed();
}

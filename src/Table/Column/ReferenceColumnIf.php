<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Table\Table;
use getoma\dbfe\Util\QueryBuilder\SelectQuery;

interface ReferenceColumnIf extends ColumnIf
{
   /** set a customized array to retrieve the selection data set
    *  to set the reference content
    * @param SelectQuery $query
    */
   public function setReferenceQuery( SelectQuery $query ): void;

   /**
    * get the content of the reference selection
    */
   public function getReferenceData(): array;

   /**
    * get the table name of this reference
    */
   public function getTable(): Table;
}

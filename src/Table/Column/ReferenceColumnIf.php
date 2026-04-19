<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Util\QueryBuilder\SelectQuery;

interface ReferenceColumnIf extends ColumnIf
{
   /** set a customized array to retrieve the selection data set
    *  to set the reference content
    * @param SelectQuery $query
    */
   public function setReferenceQuery( SelectQuery $query );

   /**
    * get the content of the reference selection
    * @return array
    */
   public function getReferenceData();

   /**
    * get the table name of this reference
    * @return Table
    */
   public function getTable();
}

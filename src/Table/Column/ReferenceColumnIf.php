<?php

namespace getoma\dbfe\Table\Column;

use Aura\SqlQuery\Common\SelectInterface;

interface ReferenceColumnIf extends ColumnIf
{
   /* set a customized array to retrieve the selection data set
    * to set the reference content
    */
   public function setReferenceQuery( SelectInterface $query ): void;

   /**
    * get the content of the reference selection
    */
   public function getReferenceData(): array;

   /**
    * get the table name of this reference
    */
   public function getTable(): \getoma\dbfe\Table\Table;
}

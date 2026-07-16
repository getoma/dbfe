<?php

namespace getoma\dbfe\Table\Column;

use getoma\dbfe\Util\ValidatedInput;

interface FileHandlerColumnIf extends ColumnIf
{
   /**
    * perform the upload of a file
    */
   public function handleUpload( ValidatedInput $data, string|array|null $rowid): void;

   /**
    * drop the given files
    */
   public function dropFiles( array $files ): void;
}

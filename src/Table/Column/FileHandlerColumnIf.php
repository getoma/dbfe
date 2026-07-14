<?php

namespace getoma\dbfe\Table\Column;

interface FileHandlerColumnIf extends ColumnIf
{
   /**
    * perform the upload of a file
    * @param array $data
    * @param string $rowid
    */
   public function handleUpload( array &$data, string $rowid): void;

   /**
    * drop the given files
    * @param array $files
    */
   public function dropFiles( array $files ): void;
}

<?php

namespace getoma\dbfe\Util\FileHandler;

/**
 * generic interface for a file upload handler
 */
interface FileHandlerIf
{
   /**
    * get the link to the file if file exists
    */
   function getFileUrl( string $file_id ): ?string;

   /**
    * get the file name if file was found
    */
   function getFileName( string $file_id ): ?string;

   /** get the file accept pattern
    * @return string
    */
   function getAccept(): string;

   /**
    * upload a file - which can be either single value or array on html form side
    * @param string $column         the column registered for the file upload
    * @param string|array|null $old_value the value currently stored in the database for this file column
    * @param string|array|null $rowid     an data row identifier that could be used as filename
    * @return string|array|null     the value to store in $column, or null if no update
    */
   function upload(string $column, string|array|null $old_value, string|array|null $rowid): array|string|null;

   /**
    * delete a already stored file
    * @param string $file_id the file identifier currently stored for this file
    */
   function delete( string $file_id ): void;
}

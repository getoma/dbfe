<?php

namespace getoma\dbfe\Util\FileHandler;

/**
 * generic interface for a file upload handler
 */
interface FileHandlerIf
{
   /** get the link to the files
    *
    * @param  string $file_id
    * @return string
    */
   function getFileUrl( string $file_id );

   /** get the file name
    * @param string $file_id
    * @return string
    */
   function getFileName( string $file_id );

   /** get the file accept pattern
    * @return string
    */
   function getAccept();

   /**
    * upload a file
    * @param string $column         the column registered for the file upload
    * @param string|null $old_value the value currently stored in the database for this file column
    * @param string|null $rowid     an data row identifier that could be used as filename
    * @return string|array          the value to store in $column, or null if no update
    */
   function upload( string $column, $old_value, $rowid );

   /**
    * delete a already stored file
    * @param string $file_id the file identifier currently stored for this file
    */
   function delete( string $file_id );
}

<?php

namespace getoma\dbfe\Util\FileHandler;

use getoma\dbfe\Util\Exception\UploadException;

/**
 * Implementation of the FileHandler Interface that stores
 * the uploaded file into a filesystem directory
 */
class DirectoryFileHandler extends BaseFileHandler
{
   protected readonly string $base_dir;

   /**
    * constructor
    */
   function __construct( string $base_dir, string $accept = '*/*' )
   {
      $this->base_dir = rtrim($base_dir, '/') . '/';
      parent::__construct($accept);
   }

   protected function storeFile(?string $row_id, array $file_data, ?string $field_value, string $file_ext): string
   {
      if( !$row_id ) throw new \LogicException('Directory File Handler requires row identifier to create a unique filename!');

      /* generate the filename: <clean(value of name column)>.<ext> */
      $filename = preg_replace( '#[^a-z0-9äüöß]#i', '', $row_id ) . '.' . $file_ext;

      /* delete the old file, if there is one */
      if( isset($field_value) )
         $this->delete($field_value);

      /* store the new file */
      if( !move_uploaded_file( $file_data['tmp_name'] , $this->base_dir . '/' . $filename ) )
      {
         throw new UploadException( 'cannot store file' );
      }

      /* return the generated filename to store it into the db */
      return $filename;
   }

   /**
    * {@inheritDoc}
    * @see FileHandlerIf::getFileUrl()
    */
   public function getFileUrl(string $file_id): ?string
   {
      $fname = $this->getFileName($file_id);

      if( isset($fname) )
      {
         $filename = $this->base_dir . $fname;
         /* get the base dir name where the file should be located from the script name *//**@var array $baseurl */
         preg_match( '#^.*/#', $_SERVER['SCRIPT_NAME'], $baseurl );

         /* return the url */
         return $baseurl[0].$filename;
      }

      return null;
   }

   /**
    * {@inheritDoc}
    * @see FileHandlerIf::getFileName()
    */
   public function getFileName( string $file_id ): ?string
   {
      return isset($file_id) && file_exists( $this->base_dir . $file_id )? $file_id : null;
   }

   public function delete(string $file_id): void
   {
      if( isset( $file_id ) && file_exists( $this->base_dir . $file_id ) )
      {
         if( !unlink( $this->base_dir . $file_id ) )
         {
            throw new UploadException( 'cannot delete old file' );
         }
      }
   }

}
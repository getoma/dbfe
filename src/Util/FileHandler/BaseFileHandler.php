<?php

namespace getoma\dbfe\Util\FileHandler;

use getoma\dbfe\Util\Exception\UploadException;

abstract class BaseFileHandler implements FileHandlerIf
{
   /** @var string */
   protected string $accept_re;

   /**
    * @param string $accept - file type accept pattern
    */
   function __construct( protected string $accept = '*/*' )
   {
      $accept = preg_replace( '#\*#', '.+', $accept); // turn wildcard pattern into regexp
      $accept = preg_replace( '#([^/]+)$#', '($1)', $accept ); // catch last part as file extension
      if( !isset($accept) ) throw new \DomainException('invalid accept pattern!');
      $this->accept_re = '#' . $accept . '#';
   }

   public function getAccept(): string
   {
      return $this->accept;
   }

   /**
    */
   public function upload(string $column, string|array|null $old_value, string|array|null $rowid): array|string|null
   {
      if( !isset($_FILES[$column]) )
      {
         throw new UploadException( "file uploads seem to be disabled! ($column)" );
      }

      if( is_array($_FILES[$column]['error'] ) )
      {
         /* fix this really stupid structure in case of multiple files */
         $file_info = [];
         foreach( $_FILES[$column] as $key => $data )
         {
            foreach( $data as $idx => $value )
            {
               $file_info[$idx][$key] = $value;
            }
         }

         $as_array = true;
      }
      else
      {
         $file_info = [ $_FILES[$column] ];
         $old_value = [ $old_value ];
         $rowid     = [ $rowid ];
         $as_array  = false;
      }

      $result = [];

      foreach( $file_info as &$file_entry )
      {
         $prev_set = count($old_value);
         $prev     = array_shift($old_value);
         $id       = array_shift($rowid);

         if( $file_entry['error'] === UPLOAD_ERR_OK )
         {
            /* check uploaded file for correct type */
            /** @var $ext string */
            if( ! preg_match( $this->accept_re, $file_entry['type'], $ext ) )
            {
               throw new UploadException( 'invalid file type' );
            }

            $result[] = $this->storeFile( $id, $file_entry, $prev, $ext[1] );
         }
         else if( $file_entry['error'] === UPLOAD_ERR_NO_FILE )
         {
            /* no file was uploaded with this field */
            if( $prev_set )
            {
               $result[] = $prev;
            }
         }
         else
         {
            throw new UploadException( null, $file_entry['error'] );
         }
      }

      return $as_array? $result : $result[0]??null;
   }

   /**
    * @param string $row_id      a constructed identifier unique to the corresponding data set where the file upload belongs to
    * @param array  $file_data   the reference to the $_FILE entry
    * @param string $field_value the currently stored value of the file upload field
    * @param string $file_ext    the file extension of the uploaded file
    * @return string|int - file handle to store into db (e.g. filename, id, ...)
    */
   abstract protected function storeFile(?string $row_id, array $file_data, ?string $field_value, string $file_ext): string|int;
}
<?php namespace dbfe;

require_once 'dbfe/Util/Exception.php';
require_once 'dbfe/Util/Query.php';

/* Exception class used for any error during file upload */
class UploadException extends \RuntimeException
{
   function __construct($message = null, $code = null, $previous = null)
   {
      if(!$message)
      {
         $errors = array_flip(get_defined_constants(true)['Core']);
         $message = $errors[$code] ?? 'unknown error';
      }
      parent::__construct( $message, $code, $previous );
   }
}

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
    * @return string                the value to store in $column, or null if no update
    */
   function upload( string $column, $old_value, $rowid );

   /**
    * delete a already stored file
    * @param string $file_id the file identifier currently stored for this file
    */
   function delete( string $file_id );
}

abstract class BaseFileHandler implements FileHandlerIf
{
   /** @var string */
   protected $accept;
   /** @var string */
   protected $accept_re;

   /**
    * @param string $accept - file type accept pattern
    */
   function __construct( string $accept = '*/*' )
   {
      $this->accept   = $accept;
      $accept = preg_replace( '#\*#', '.+', $accept); // turn wildcard pattern into regexp
      $accept = preg_replace( '#([^/]+)$#', '($1)', $accept ); // catch last part as file extension
      if( !isset($accept) ) die('invalid accept pattern!');
      $this->accept_re = '#' . $accept . '#';
   }

   public function getAccept()
   {
      return $this->accept;
   }

   /**
    * {@inheritDoc}
    * @see FileHandlerIf::upload()
    */
   public function upload( string $column, $old_value, $rowid )
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
    * @param string $file_data   the reference to the $_FILE entry
    * @param string $field_value the currently stored value of the file upload field
    * @param string $file_ext    the file extension of the uploaded file
    */
   abstract protected function storeFile( $row_id, array &$file_data, $field_value, string $file_ext );
}

/**
 * Implementation of the FileHandler Interface that stores
 * the uploaded file into a filesystem directory
 */
class DirectoryFileHandler extends BaseFileHandler
{
   /** @var string */
   protected $base_dir;
   /** @var string */
   protected $accept;
   /** @var string */
   protected $accept_re;

   /**
    * @param string $base_dir
    * @param string $accept
    */
   function __construct( string $base_dir, string $accept = '*/*' )
   {
      parent::__construct($accept);
      $this->base_dir = $base_dir . '/';
   }

   protected function storeFile( $row_id, array &$file_data, $field_value, string $file_ext )
   {
      if( empty($row_id) ) throw new \LogicException('Directory File Handler requires row identifier to create a unique filename!');

      /* generate the filename: <clean(value of name column)>.<ext> */
      $filename = preg_replace( '#[^a-z0-9äüöß]#i', '', $row_id ) . '.' . $file_ext;

      /* delete the old file, if there is one */
      if( isset($field_value) )
         $this->delete($field_value);

      /* store the new file */
      if( ! move_uploaded_file( $file_data['tmp_name'] , $this->base_dir . '/' . $filename ) )
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
   public function getFileUrl($file_id)
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
   public function getFileName( $file_id )
   {
      return isset($file_id) && file_exists( $this->base_dir . $file_id )? $file_id : null;
   }

   public function delete(string $file_id)
   {
      if( isset( $file_id ) && file_exists( $this->base_dir . $file_id ) )
      {
         if( ! unlink( $this->base_dir . $file_id ) )
         {
            throw new UploadException( 'cannot delete old file' );
         }
      }
   }

}

/**
 * Implementation of the FileHandler Interface that stores
 * the uploaded file into a dedicated 'Uploads' table
 */
class dbFileHandler extends BaseFileHandler
{
   /** @var \PDO */
   protected $dbh  = null;
   /** @var \PDOStatement */
   protected $stmt = null;
   /** @var \PDOStatement */
   protected $del_stmt = null;
   /** @var \PDOStatement */
   protected $info_stmt = null;
   /** @var string */
   protected $link_template = '';
   /** @var array */
   protected $info = [];
   
   function __construct( \PDO $dbh, string $template = '', string $table_name = 'Uploads', string $accept = '*/*' )
   {
      parent::__construct($accept);
      
      $this->dbh  = $dbh;
      $this->link_template = $template;
      
      $query = new InsertQuery();
      $query->table_spec = $table_name;
      $query->columns = [ 'Uploads_ID' => '?', 'Name' => '?', 'Type' => '?', 'Size' => '?', 'Content' => '?' ];
      $query->on_duplicate = true;
      $this->stmt = $dbh->prepare($query->asString());
      
      $del = new DeleteQuery();
      $del->table_spec = $table_name;
      $del->filter     = [ 'Uploads_ID' => '?' ];
      $this->del_stmt  = $dbh->prepare($del->asString());
      
      $info = new SelectQuery();
      $info->table_spec = $table_name;
      $info->filter     = [ 'Uploads_ID' => '?' ];
      $info->columns    = ['Name', 'Type', 'Size'];
      $this->info_stmt  = $dbh->prepare($info->asString());
      
      $this->dbh->exec(<<<'MYSQL'
            CREATE TABLE if not exists Uploads (
              Uploads_ID int NOT NULL AUTO_INCREMENT,
              Name varchar(127) NOT NULL,
              Type varchar(30) NOT NULL,
              Size int NOT NULL,
              Content mediumblob NOT NULL,
            PRIMARY KEY (Uploads_ID) )
      MYSQL
         );
   }
   
   /**
    * {@inheritDoc}
    * @see \dbfe\BaseFileHandler::store_file()
    */
   protected function storeFile( $row_id, array &$file_data, $field_value, string $file_ext )
   {
      /* read in the file */
      $fname   = $file_data['tmp_name'];
      $fh      = fopen( $fname, 'rb' );
      $content = fread($fh, filesize($fname));
      fclose($fh);
      
      if( !$this->stmt->execute( [ $field_value, $file_data['name'], $file_data['type'], $file_data['size'], $content ] ) )
      {
         throw new DatabaseUpdateError("cannot upload file - " . $this->stmt->$stmt->errorInfo()[2] );
      }
      
      return $this->dbh->lastInsertId();
   }
   
   /**
    * {@inheritDoc}
    * @see FileHandlerIf::getFileUrl()
    */
   public function getFileUrl($file_id)
   {
      return sprintf( $this->link_template, $file_id );
   }
   
   /**
    * {@inheritDoc}
    * @see FileHandlerIf::getFileName()
    */
   public function getFileName( $file_id )
   {
      if( !isset($file_id) ) return null;
      
      if( !isset($this->info[$file_id]) )
      {
         if( $this->info_stmt->execute([$file_id]) )
         {
            $this->info[$file_id] = $this->info_stmt->fetch(\PDO::FETCH_ASSOC);
            $this->info_stmt->closeCursor();
         }
         else
         {
            return null;
         }
      }
      return $this->info[$file_id]['Name'];
   }
   
   public function delete(string $file_id)
   {
      if( !$this->del_stmt->execute( [$file_id] ) )
      {
         throw new DatabaseUpdateError("cannot delete file - " . $this->del_stmt->errorInfo()[2] );
      }
   }
}

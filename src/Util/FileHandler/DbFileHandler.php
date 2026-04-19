<?php

namespace getoma\dbfe\Util\FileHandler;

use getoma\dbfe\Util\QueryBuilder\InsertQuery;
use getoma\dbfe\Util\QueryBuilder\DeleteQuery;
use getoma\dbfe\Util\QueryBuilder\SelectQuery;

use getoma\dbfe\Util\Exception\DatabaseUpdateError;

/**
 * Implementation of the FileHandler Interface that stores
 * the uploaded file into a dedicated 'Uploads' table
 */
class DbFileHandler extends BaseFileHandler
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
         throw new DatabaseUpdateError("cannot upload file - " . $this->stmt->errorInfo()[2] );
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
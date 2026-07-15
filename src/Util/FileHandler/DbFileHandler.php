<?php

namespace getoma\dbfe\Util\FileHandler;

use Aura\SqlQuery\QueryFactory;
use getoma\dbfe\Util\Exception\DatabaseUpdateError;

/**
 * Implementation of the FileHandler Interface that stores
 * the uploaded file into a dedicated 'Uploads' table
 */
class DbFileHandler extends BaseFileHandler
{
   /** buffer retrieved file info */
   protected array $info = [];

   /**
    * lazy-prepare any statements
    */

   private \PDOStatement $insert_stmt {
      get {
         if( !isset($this->insert_stmt) )
         {
            /** @var \Aura\SqlQuery\Mysql\Insert $query */
            $query = $this->queryFactory->newInsert();
            $query->into($this->table_name);
            $query->cols([ 'Name', 'Type', 'Size', 'Content' ]);
            $this->insert_stmt = $this->dbh->prepare($query->getStatement());
         }
         return $this->insert_stmt;
      }
   }

   private \PDOStatement $update_stmt {
      get {
         if( !isset($this->update_stmt) )
         {
            $query = $this->queryFactory->newUpdate();
            $query->table($this->table_name);
            $query->cols(['Name', 'Type', 'Size', 'Content']);
            $query->where('Uploads_ID=:Uploads_ID');
            $this->update_stmt = $this->dbh->prepare($query->getStatement());
         }
         return $this->update_stmt;
      }
   }

   private \PDOStatement $info_stmt {
      get {
         if( !isset($this->info_stmt) )
         {
            $query = $this->queryFactory->newSelect();
            $query->from($this->table_name);
            $query->where('Uploads_ID=?');
            $query->cols(['Name', 'Type', 'Size']);
            $this->info_stmt = $this->dbh->prepare($query->getStatement());
         }
         return $this->info_stmt;
      }
   }

   private \PDOStatement $del_stmt {
      get {
         if( !isset($this->del_stmt) )
         {
            $query = $this->queryFactory->newDelete();
            $query->from($this->table_name);
            $query->where('Uploads_ID=?');
            $this->del_stmt = $this->dbh->prepare($query->getStatement());
         }
         return $this->del_stmt;
      }
   }

   /**
    * constructor
    */
   function __construct(
      protected readonly \PDO $dbh,
      protected readonly QueryFactory $queryFactory,
      protected readonly string $template = '',
      protected readonly string $table_name = 'Uploads',
      string $accept = '*/*',
   )
   {
      parent::__construct($accept);

      $this->dbh->exec(<<<'MYSQL'
            CREATE TABLE if not exists Uploads (
              Uploads_ID int NOT NULL AUTO_INCREMENT,
              Name varchar(127) NOT NULL,
              Type varchar(30) NOT NULL,
              Size int NOT NULL,
              Content mediumblob NOT NULL,
            PRIMARY KEY (Uploads_ID) )
      MYSQL);
   }

   /**
    */
   protected function storeFile(?string $row_id, array $file_data, ?string $field_value, string $file_ext): string
   {
      /* read in the file */
      $fname   = $file_data['tmp_name'];
      $fh      = fopen( $fname, 'rb' );
      $content = fread($fh, filesize($fname));
      fclose($fh);

      $values = [
         'Name'    => $file_data['name'],
         'Type'    => $file_data['type'],
         'Size'    => $file_data['size'],
         'Content' => $content
      ];

      $result = $field_value? $this->update_stmt->execute(['Uploads_ID' => $field_value] + $values) : $this->insert_stmt->execute($values);
      if( !$result )
      {
         throw new DatabaseUpdateError("cannot upload file - " . $this->insert_stmt->errorInfo()[2] );
      }
      return $field_value ?: $this->dbh->lastInsertId();
   }

   /**
    */
   public function getFileUrl(string $file_id): ?string
   {
      return sprintf( $this->template, $file_id );
   }

   /**
    */
   public function getFileName(string $file_id): ?string
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

   /**
    */
   public function delete(string $file_id): void
   {
      if( !$this->del_stmt->execute( [$file_id] ) )
      {
         throw new DatabaseUpdateError("cannot delete file - " . $this->del_stmt->errorInfo()[2] );
      }
   }
}
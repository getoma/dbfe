<?php namespace dbfe;

require_once 'dbfe/Util/Exception.php';

interface LabelHandlerIf
{
   /**
    * get the label for a specific key
    * @param string $key
    */
   function get( string $key, string $category = '' );
}

/**
 * default label handler automatically used by dbfe when no handler given
 */
class DummyLabelHandler implements LabelHandlerIf
{
   public function get(string $key, string $category = '')
   {
      return $key;
   }
}

class dbLabelHandler implements LabelHandlerIf
{
   /** @var string */
   public $not_found_template = "%s";
   /** @var bool */
   public $add_not_found = true;
   /** @var \PDOStatement */
   protected $stmt;
   /** @var \PDOStatement */
   protected $add_stmt;
   
   function __construct( \PDO $dbh, string $table )
   {
      $dbh->exec(<<<"tabledef"
create table if not exists $table (
   subject varchar(127) not null primary key,
   text varchar(127) null
) default character set utf8mb4 default collate utf8mb4_unicode_ci
tabledef
         );
      
      $this->stmt     = $dbh->prepare( "select text from $table where subject=?" );
      $this->add_stmt = $dbh->prepare( "insert ignore into $table (subject) values (?)" );
   }
   
   public function get(string $key, string $category = '')
   {
      $result = null;
      $db_key = $category? $category.'.'.$key : $key;
      /* retrieve the key from the translation table */
      if( $this->stmt->execute([ $db_key ]) )
      {
         /* check if an entry was found */
         if( $this->stmt->rowCount() === 0 )
         {
            /* no entry found: add it to the table if requested */
            if( $this->add_not_found )
            {
               $this->add_stmt->bindValue(1, $db_key);
               $this->add_stmt->execute();
            }
         }
         else
         {
            /* entry found: get and return it */
            $data = $this->stmt->fetchColumn(0);
            if( !is_null($data) )
            {
               $result = $data;
            }
         }
      }
      
      $this->stmt->closeCursor();
      
      /* apply the "not found" template if no entry found */
      if( !isset($result) )
      {
         $result = sprintf( $this->not_found_template, $key );
      }
      
      return $result;
   }
}

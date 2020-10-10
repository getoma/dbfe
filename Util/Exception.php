<?php namespace dbfe;

class DatabaseError extends \LogicException
{
   /* exception to notify about errors in interaction with
    * database - e.g. features used in database not (yet) supported
    * by script
    * --> should never actually be thrown after development
    */
}

class DatabaseUpdateError extends \RuntimeException
{
   /* an database update was rejected by the database
    * (most probably due to foreign key constraint violations)
    */
}

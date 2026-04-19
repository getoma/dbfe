<?php

namespace getoma\dbfe\Util\Exception;

/**
 * an database update was rejected by the database
 * (most probably due to foreign key constraint violations)
 */
class DatabaseUpdateError extends \RuntimeException
{

}
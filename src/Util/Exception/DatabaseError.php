<?php

namespace getoma\dbfe\Util\Exception;

/**
 * exception to notify about errors in interaction with
 * database - e.g. features used in database not (yet) supported
 * by script
 * --> should never actually be thrown after development
 */
class DatabaseError extends \LogicException
{
}

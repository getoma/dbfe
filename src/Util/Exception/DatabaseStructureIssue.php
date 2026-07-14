<?php declare(strict_types=1);

namespace getoma\dbfe\Util\Exception;

/**
 * exception thrown if the database structure does not comply
 * with the requirements of DBFE
 * e.g. if tables are missing an ID column.
 */
final class DatabaseStructureIssue extends \LogicException
{
}
<?php

namespace Kaliop\eZMigrationBundle\API\Exception;

/**
 * Throw this exception in any step to suspend the migration
 */
class MigrationSuspendedException extends \Exception
{
    public function __construct($message = "", $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

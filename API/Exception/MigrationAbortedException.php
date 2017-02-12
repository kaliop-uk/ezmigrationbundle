<?php

namespace Kaliop\eZMigrationBundle\API\Exception;

use Kaliop\eZMigrationBundle\API\Value\Migration;

/**
 * Throw this exception in any step to abort the migration and mark it as finished in either DONE or SKIPPED status
 */
class MigrationAbortedException extends \Exception
{
    public function __construct($message = "", $status = Migration::STATUS_DONE, \Exception $previous = null)
    {
        if ($status !== Migration::STATUS_DONE && $status !== Migration::STATUS_SKIPPED && $status !== Migration::STATUS_FAILED) {
            throw new \Exception("Unsupported migration status $status in MigrationAbortedException");
        }

        parent::__construct($message, $status, $previous);
    }
}

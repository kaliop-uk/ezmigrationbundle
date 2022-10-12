<?php

namespace Kaliop\eZMigrationBundle\API\Exception;

/**
 * Throw this exception in any step when in a loop, and you want the loop to stop, without the migration aborting
 */
class LoopBreakException extends MigrationBundleException
{
    public function __construct($message = "", $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

<?php

namespace Kaliop\eZMigrationBundle\API\Exception;

/**
 * Throw this exception in any step when the step has not been executed
 */
class MigrationStepSkippedException extends MigrationBundleException
{
    public function __construct($message = "", $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

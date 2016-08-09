<?php

namespace Kaliop\eZMigrationBundle\API\Exception;

class MigrationStepExecutionException extends \Exception
{
    public function __construct($message = "", $step = 0, \Exception $previous = null)
    {
        $message = "Error in execution of step $step: " . $message;

        parent::__construct($message, $step, $previous);
    }
}
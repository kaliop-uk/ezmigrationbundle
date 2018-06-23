<?php

namespace Kaliop\eZMigrationBundle\API\Exception;

class AfterMigrationExecutionException extends \Exception
{
    public function __construct($message = "", $step = 0, \Exception $previous = null)
    {
        $message = "Error after execution of step $step: " . $message;

        parent::__construct($message, $step, $previous);
    }
}

<?php

namespace Kaliop\eZMigrationBundle\API\Exception;

class AfterMigrationExecutionException extends MigrationBundleException
{
    public function __construct($message = "", $step = 0, \Exception $previous = null)
    {
        if ($step > 0) {
            $message = "Error after execution of step $step: " . $message;
        } else {
            $message = "Error after execution of migration: " . $message;
        }

        parent::__construct($message, $step, $previous);
    }
}

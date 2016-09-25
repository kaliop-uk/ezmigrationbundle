<?php

namespace Kaliop\eZMigrationBundle\Tests\helper;

use Kaliop\eZMigrationBundle\API\Event\BeforeStepExecutionEvent;

class BeforeStepExecutionListener
{
    static $executions = 0;

    public function onBeforeStepExecution(BeforeStepExecutionEvent $event)
    {
        self::$executions++;
    }

    public static function getExecutions()
    {
        return self::$executions;
    }
}

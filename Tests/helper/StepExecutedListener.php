<?php

namespace Kaliop\eZMigrationBundle\Tests\helper;

use Kaliop\eZMigrationBundle\API\Event\StepExecutedEvent;

class StepExecutedListener
{
    static $executions = 0;

    public function onStepExecuted(StepExecutedEvent $event)
    {
        if ($event->getResult() === null) {
            throw new \Exception('Received null as step execution event result');
        }
        self::$executions++;
    }

    public static function getExecutions()
    {
        return self::$executions;
    }
}

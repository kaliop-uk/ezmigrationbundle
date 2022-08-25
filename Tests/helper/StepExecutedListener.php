<?php

namespace Kaliop\eZMigrationBundle\Tests\helper;

use Kaliop\eZMigrationBundle\API\Event\StepExecutedEvent;
use Kaliop\eZMigrationBundle\API\Collection\AbstractCollection;

class StepExecutedListener
{
    static $executions = 0;

    public function onStepExecuted(StepExecutedEvent $event)
    {
        if (isset($event->getStep()->dsl['allow_null_results']) && $event->getStep()->dsl['allow_null_results']) {
            self::$executions++;
            return;
        }

        $result = $event->getResult();

        if ($event->getResult() === null && $event->getStep()->type !== 'assert' && $event->getStep()->type !== 'void'
            && $event->getStep()->type !== 'service' && $event->getStep()->type !== 'php') {
            throw new \Exception('Received null as step execution event result');
        }

        if (is_object($result) && $result instanceof AbstractCollection) {
            if (count($result) == 0) {
                throw new \Exception('Step execution resulted in an empty collection. Step not applied?');
            }
        }

        self::$executions++;
    }

    public static function getExecutions()
    {
        return self::$executions;
    }
}

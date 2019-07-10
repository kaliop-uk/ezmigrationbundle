<?php

namespace Kaliop\eZMigrationBundle\Tests\helper;

use Kaliop\eZMigrationBundle\API\Event\MigrationGeneratedEvent;

class MigrationGeneratedListener
{
    static $event;

    public function onMigrationGenerated(MigrationGeneratedEvent $event)
    {
        self::$event = $event;
    }

    public static function getLastEvent()
    {
        return self::$executions;
    }
}

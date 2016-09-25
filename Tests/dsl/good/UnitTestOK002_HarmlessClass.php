<?php

use Kaliop\eZMigrationBundle\API\MigrationInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use \eZ\Publish\Core\Persistence\Database\SelectQuery;

class HarmlessClass implements MigrationInterface
{
    public static function execute(ContainerInterface $container)
    {
        // return non-null to make sure that the listener tests pass
        return 1;
    }
}

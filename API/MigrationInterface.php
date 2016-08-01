<?php

namespace Kaliop\eZMigrationBundle\API;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * All migrations done as php scripts need to implement this interface
 */
interface MigrationInterface
{
    /**
     * Execute the migration.
     * @param ContainerInterface $container The container is passed as a way to access everything else
     */
    public static function execute(ContainerInterface $container);
}

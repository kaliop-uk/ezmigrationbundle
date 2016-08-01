<?php

namespace Kaliop\eZMigrationBundle\API;

use Kaliop\eZMigrationBundle\API\Collection\MigrationCollection;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;

/**
 * Implemented by classes which store details of the executed migrations
 */
interface StorageHandlerInterface
{
    /**
     * @return MigrationCollection sorted from oldest to newest
     */
    public function loadMigrations();

    /**
     * Starts a migration
     * @param MigrationDefinition $migrationDefinition
     * @return Migration
     * @throws \Exception If the migration was already executing
     */
    public function startMigration(MigrationDefinition $migrationDefinition);

    /**
     * Ends a migration
     * @param Migration $migration
     * @throws \Exception If the migration was not started
     */
    public function endMigration(Migration $migration);
}

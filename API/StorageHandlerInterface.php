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
     * @param string $migrationName
     * @return Migration|null
     */
    public function loadMigration($migrationName);

    /**
     * Creates and stores a new migration (leaving it in TODO status)
     *
     * @param MigrationDefinition $migrationDefinition
     * @return mixed
     * @throws \Exception If the migration exists already
     */
    public function addMigration(MigrationDefinition $migrationDefinition);

    /**
     * Starts a migration (creates and stores it, in STARTED status)
     *
     * @param MigrationDefinition $migrationDefinition
     * @return Migration
     * @throws \Exception If the migration was already executed, skipped or executing
     */
    public function startMigration(MigrationDefinition $migrationDefinition);

    /**
     * Ends a migration (updates it)
     *
     * @param Migration $migration
     * @param bool $force When true, the migration will be updated even if it was not in 'started' status
     * @throws \Exception If the migration was not started (unless $force=true)
     */
    public function endMigration(Migration $migration, $force=false);

    /**
     * Removes a migration from storage (regardless of its status)
     *
     * @param Migration $migration
     */
    public function deleteMigration(Migration $migration);

    /**
     * Removes all migration from storage (regardless of their status)
     */
    public function deleteMigrations();

    /**
     * Skips a migration (upserts it, in SKIPPED status)
     *
     * @param MigrationDefinition $migrationDefinition
     * @return Migration
     * @throws \Exception If the migration was already executed, skipped or executing
     */
    public function skipMigration(MigrationDefinition $migrationDefinition);

}

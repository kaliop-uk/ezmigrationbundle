<?php

namespace Kaliop\eZMigrationBundle\API;

use Kaliop\eZMigrationBundle\API\Collection\MigrationCollection;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;

/**
 * Implemented by classes which store details of the executing/executed migrations
 */
interface StorageHandlerInterface
{
    /**
     * @param int $limit 0 or below will be treated as 'no limit'
     * @param int $offset
     * @return MigrationCollection sorted from oldest to newest
     */
    public function loadMigrations($limit = null, $offset = null);

    /**
     * @param int $status see the STATUS_ constants
     * @param int $limit 0 or below will be treated as 'no limit'
     * @param int $offset
     * @return MigrationCollection sorted from oldest to newest
     */
    public function loadMigrationsByStatus($status, $limit = null, $offset = null);

    /// @todo add loadMigrationsByPaths when we can break BC
    //public function loadMigrationsByPaths(array $paths, $limit = null, $offset = null);

    /**
     * @param string $migrationName
     * @return Migration|null
     */
    public function loadMigration($migrationName);

    /**
     * Creates and stores a new migration (leaving it in TODO status)
     *
     * @param MigrationDefinition $migrationDefinition
     * @return Migration
     * @throws \Exception If the migration exists already
     */
    public function addMigration(MigrationDefinition $migrationDefinition);

    /**
     * Starts a migration (creates and stores it, in STARTED status)
     *
     * @param MigrationDefinition $migrationDefinition
     * @param bool $force
     * @return Migration
     * @throws \Exception If the migration was already executing. Also if it as already (executed|skipped) unless $force is true
     */
    public function startMigration(MigrationDefinition $migrationDefinition, $force = false);

    /**
     * Ends a migration (updates it)
     *
     * @param Migration $migration
     * @param bool $force When true, the migration will be updated even if it was not in 'started' status
     * @throws \Exception If the migration was not started (unless $force=true)
     */
    public function endMigration(Migration $migration, $force = false);

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

    /**
     * Resumes a migration (updates it, from SUSPENDED to status STARTED)
     *
     * @param Migration $migration
     * @return Migration
     * @throws \Exception If the migration was not present or not suspended
     */
    public function resumeMigration(Migration $migration);
}

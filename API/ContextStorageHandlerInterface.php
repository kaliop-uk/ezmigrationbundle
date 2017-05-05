<?php

namespace Kaliop\eZMigrationBundle\API;

use Kaliop\eZMigrationBundle\API\Collection\MigrationCollection;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;

/**
 * Implemented by classes which store details of the executing migrations contexts
 */
interface ContextStorageHandlerInterface
{
    /**
     * @param string $migrationName
     * @return array|null
     */
    public function loadMigrationContext($migrationName);

    /**
     * Stores a migration context
     *
     * @param MigrationDefinition $migrationDefinition
     * @return Migration
     * @throws \Exception If the migration exists already
     */
    public function storeMigrationContext(MigrationDefinition $migrationDefinition);

    /**
     * Removes a migration context from storage (regardless of the migration status)
     *
     * @param Migration $migration
     */
    public function deleteMigrationContext(Migration $migration);

    /**
     * Removes all migration contexts from storage (regardless of the migration status)
     */
    public function deleteMigrationContexts();
}

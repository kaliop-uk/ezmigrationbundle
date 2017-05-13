<?php

namespace Kaliop\eZMigrationBundle\API;

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
     * @param string $migrationName
     * @param array $context
     */
    public function storeMigrationContext($migrationName, array $context);

    /**
     * Removes a migration context from storage (regardless of the migration status)
     *
     * @param string $migrationName
     */
    public function deleteMigrationContext($migrationName);

    /**
     * Removes all migration contexts from storage (regardless of the migration status)
     */
    public function deleteMigrationContexts();
}

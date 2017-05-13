<?php

namespace Kaliop\eZMigrationBundle\API;

use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;

/**
 * Implemented by classes which have 'context' data that should be stored/restored when migrations are suspended
 */
interface ContextProviderInterface
{
    /**
     * @param string $migrationName
     * @return array|null
     */
    public function getCurrentContext($migrationName);

    /**
     * @param string $migrationName
     * @param array $context
     */
    public function restoreContext($migrationName, array $context);
}

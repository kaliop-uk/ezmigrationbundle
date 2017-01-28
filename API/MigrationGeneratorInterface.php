<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Interface that all migration definition handlers need to implement if they can generate a migration.
 */
interface MigrationGeneratorInterface
{
    /**
     * Generate a migration
     *
     * @param string $matchType
     * @param string|string[] $matchValue
     * @param string $mode
     * @return array Migration data
     * @throws \Exception
     */
    public function generateMigration($matchType, $matchValue, $mode);
}

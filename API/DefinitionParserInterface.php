<?php

namespace Kaliop\eZMigrationBundle\API;

use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;

/**
 * Interface that all migration definition handlers need to implement.
 *
 * A definition handler takes care of decoding (loading) a specific file format used to define a migration version
 */
interface DefinitionParserInterface
{
    /**
     * Tells whether the given file can be handled by this handler, by checking e.g. the suffix
     *
     * @param string $migrationName typically a filename
     * @return bool
     */
    public function supports($migrationName);

    /**
     * Parses a migration definition, and returns the list of actions to take, as a new definition object.
     * The new definition should have either status STATUS_PARSED and some steps, or STATUS_INVALID and an error message
     *
     * @param MigrationDefinition $definition
     * @return \Kaliop\eZMigrationBundle\API\Value\MigrationDefinition
     */
    public function parseMigrationDefinition(MigrationDefinition $definition);
}

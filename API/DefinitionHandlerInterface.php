<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Interface that all migration definition handlers need to implement.
 *
 * A definition handler takes care of decoding (loading) a specific file format used to define a migration version
 */
interface DefinitionHandlerInterface
{
    /**
     * Tells whether the given file can be handled by this handler, by checking e.g. the suffix
     *
     * @param string $migrationName typically a filename
     * @return bool
     */
    public function supports($migrationName);

    /**
     * Analyze a migration file to determine whether it is valid or not.
     * This will be only called on files that pass the supports() call
     *
     * @param string $migrationName typically a filename
     * @param string $contents
     * @throws \Exception if the file is not valid for any reason
     *
     * @todo shall we prescribe a specific exception to be thrown ?
     */
    public function isValidMigrationDefinition($migrationName, $contents);

    /**
     * Parses a migration definition file, and returns the list of actions to take
     *
     * @param string $migrationName typically a filename
     * @param string $contents
     * @return \Kaliop\eZMigrationBundle\API\Value\MigrationDefinition
     */
    public function parseMigrationDefinition($migrationName, $contents);
}

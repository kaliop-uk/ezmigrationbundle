<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionHandler;

use Kaliop\eZMigrationBundle\API\DefinitionHandlerInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

class PHPDefinitionHandler implements DefinitionHandlerInterface
{
    /**
     * Tells whether the given file can be handled by this handler, by checking e.g. the suffix
     *
     * @param string $migrationName typically a filename
     * @return bool
     */
    public function supports($migrationName)
    {
        return pathinfo($migrationName, PATHINFO_EXTENSION) == 'php';
    }

    /**
     * Analyze a migration file to determine whether it is valid or not.
     * This will be only called on files that pass the supports() call
     *
     * @param string $migrationName typically a filename
     * @param string $contents
     * @throws \Exception if the file is not valid for any reason
     */
    public function isValidMigrationDefinition($migrationName, $contents)
    {
        /// @todo validate that php file is ok, contains a class with good interface ?
    }

    /**
     * Parses a migration definition file, and returns the list of actions to take
     *
     * @param string $migrationName typically a filename
     * @param string $contents
     * @return \Kaliop\eZMigrationBundle\API\Value\MigrationDefinition
     */
    public function parseMigrationDefinition($migrationName, $contents)
    {
        /// @todo !!!
        return new MigrationDefinition(
            basename($fileName),
            array(
                //new MigrationStep('sql', array($dbType => file_get_contents($fileName)))
            )
        );
    }
}

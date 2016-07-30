<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionParser;

use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;

class PHPDefinitionParser implements DefinitionParserInterface
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
     * Parses a migration definition file, and returns the list of actions to take
     *
     * @param MigrationDefinition $definition
     * @return MigrationDefinition
     */
    public function parseMigrationDefinition(MigrationDefinition $definition)
    {
        /// @todo validate that php file is ok, contains a class with good interface ?

        /// @todo !!!
        return new MigrationDefinition(
            $definition->name,
            $definition->path,
            $definition->rawDefinition,
            MigrationDefinition::STATUS_PARSED,
            array(
                //new MigrationStep('sql', array($dbType => file_get_contents($fileName)))
            )
        );
    }
}

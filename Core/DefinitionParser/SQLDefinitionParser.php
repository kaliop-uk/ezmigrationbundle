<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionParser;

use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

class SQLDefinitionParser implements DefinitionParserInterface
{
    /**
     * Array of supported DB servers
     * @var array $supportedDatabases
     */
    private $supportedDatabases;

    public function __construct(array $supportedDatabases = array('mysql', 'postgres'))
    {
        $this->supportedDatabases = $supportedDatabases;
    }

    /**
     * Tells whether the given file can be handled by this handler, by checking e.g. the suffix
     *
     * @param string $migrationName typically a filename
     * @return bool
     */
    public function supports($migrationName)
    {
        return pathinfo($migrationName, PATHINFO_EXTENSION) == 'sql';
    }

    /**
     * Parses a migration definition file, and returns the list of actions to take
     *
     * @param MigrationDefinition $definition
     * @return MigrationDefinition
     */
    public function parseMigrationDefinition(MigrationDefinition $definition)
    {
        $dbType = $this->getDBFromFile($definition->name);

        if (!in_array($dbType, $this->supportedDatabases))
        {
            return new MigrationDefinition(
                $definition->name,
                $definition->path,
                $definition->rawDefinition,
                MigrationDefinition::STATUS_INVALID,
                array(),
                "Unsupported or missing database: '$dbType'"
            );
        }

        return new MigrationDefinition(
            $definition->name,
            $definition->path,
            $definition->rawDefinition,
            MigrationDefinition::STATUS_PARSED,
            array(
                new MigrationStep('sql', array($dbType => $definition->rawDefinition))
            )
        );
    }

    protected function getDBFromFile($fileName)
    {
        $parts = explode( '_', $fileName);
        return isset($parts[1]) ? $parts[1] : null;
    }
}

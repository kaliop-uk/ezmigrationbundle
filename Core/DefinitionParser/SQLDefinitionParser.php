<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionParser;

use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
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
                "Unsupported or missing database: '$dbType'. The database name is the part of the filename after the 1st underscore"
            );
        }

        return new MigrationDefinition(
            $definition->name,
            $definition->path,
            $definition->rawDefinition,
            MigrationDefinition::STATUS_PARSED,
            array(
                new MigrationStep('sql', array($dbType => $definition->rawDefinition), array('path' => $definition->path))
            )
        );
    }

    // allow both aaaa_mysql_etc.sql and aaaa_mysql.sql
    protected function getDBFromFile($fileName)
    {
        $parts = explode('_', preg_replace('/\.sql$/', '', $fileName));
        return isset($parts[1]) ? $parts[1] : null;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionHandler;

use Kaliop\eZMigrationBundle\API\DefinitionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

use eZ\Publish\Core\Persistence\Legacy\EzcDbHandler;

class SQLDefinitionHandler implements DefinitionHandlerInterface, ContainerAwareInterface
{
    /**
     * Path to the sql migration file
     *
     * @var string
     */
    public $sqlFile;

    /**
     * Array of supported DB servers
     *
     * @FIXME: Do we want to move this to a config file?
     * @var array
     */
    private $supportedDatabases = array('mysql', 'postgres');

    /**
     * The service container object.
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * Sets the Container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     *
     * @api
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
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
     * Analyze a migration file to determine whether it is valid or not.
     * This will be only called on files that pass the supports() call
     *
     * @param string $migrationName typically a filename
     * @param string $contents
     * @throws \Exception if the file is not valid for any reason
     */
    public function isValidMigrationDefinition($migrationName, $contents)
    {
        /// @todo use a specific exception type
        if ($this->getDBFromFile($migrationName) == null) {
            throw new \Exception("Migration definition file '$migrationName' does not contain a database name");
        }
    }

    /**
     * Parses a migration definition file, and returns the list of actions to take
     *
     * @param string $migrationName typically a filename
     * @param string $contents
     * @return MigrationDefinition
     */
    public function parseMigrationDefinition($migrationName, $contents)
    {
        $dbType = $this->getDBFromFile($migrationName);
        return new MigrationDefinition(
            $migrationName,
            array(
                new MigrationStep('sql', array($dbType => $contents))
            )
        );
    }

    protected function getDBFromFile($fileName)
    {
        $parts = explode( '_', $fileName);
        return isset($parts[1]) ? $parts[1] : null;
    }
}

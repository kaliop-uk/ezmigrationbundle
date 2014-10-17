<?php
namespace Kaliop\Migration\BundleMigrationBundle\Core\DefinitionHandlers;


use Kaliop\Migration\BundleMigrationBundle\Interfaces\BundleAwareInterface;
use Kaliop\Migration\BundleMigrationBundle\Interfaces\VersionInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use eZ\Publish\Core\Persistence\Legacy\EzcDbHandler;

class SQLDefinitionHandler implements VersionInterface, ContainerAwareInterface, BundleAwareInterface
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
    private $supportedDatabases = array( 'mysql', 'postgres' );

    /**
     * The service container object.
     *
     * @var ContainerInterface
     */
    private $container;

    /**
     * The bundle object the migration file is for.
     *
     * @var BundleInterface
     */
    private $bundle;

    /**
     * Sets the bundle
     * @param BundleInterface $bundle
     * @api
     */
    public function setBundle(BundleInterface $bundle = null)
    {
        $this->bundle = $bundle;
    }

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
     * Execute a version definition.
     *
     * For now simply prepare a PDO Statement from the contents of the sql file and execute it.
     */
    public function execute()
    {
        $sqlCommands = file_get_contents( $this->sqlFile);

        /** @var $connection \ezcDbHandler */
        $connection = $this->container->get('ezpublish.connection');

        $statement = $connection->prepare($sqlCommands);

        $statement->execute();
    }

    /**
     * Check if the sqlFile is a valid migration file.
     *
     * The file name should use the YYYYMMDDHHMMSS_dbtype_some_other_text.sql format.
     *
     * If dbtype is missing or not one of SQLDefinitionHandler::$supportedDatabases then the function will return false.
     *
     * @throws \InvalidArgumentException
     * @return boolean
     */
    public function isValidMigration()
    {
        if (empty($this->sqlFile)) {
            throw new \InvalidArgumentException('Missing sql file from migration.');
        }

        $parts = explode( '_', $this->sqlFile);

        if ( count($parts) < 2 || !in_array( $parts[1], $this->supportedDatabases)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the migration is for the currently used DB.
     *
     * @param \eZ\Publish\Core\Persistence\Legacy\EzcDbHandler $connection
     * @throws \InvalidArgumentException
     * @return boolean
     */
    public function isMigrationForDBServer( EzcDbHandler $connection)
    {
        if (empty($this->sqlFile)) {
            throw new \InvalidArgumentException('Missing sql file from migration.');
        }

        $parts = explode( '_', $this->sqlFile);

        $dbName = $connection->getName();

        if(count($parts)< 2 || $parts[1] != $dbName ) {
            return false;
        }

        return true;
    }
}
<?php

namespace Kaliop\eZMigrationBundle\Core;

use eZ\Bundle\EzPublishCoreBundle\Console\Application;
use Kaliop\eZMigrationBundle\Core\DefinitionParser\SQLDefinitionParser;
use Kaliop\eZMigrationBundle\Core\DefinitionParser\YamlDefinitionParser;
use Kaliop\eZMigrationBundle\API\BundleAwareInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use eZ\Publish\Core\Persistence\Legacy\EzcDbHandler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Main configuration object holding settings for the migrations.
 */
class Configuration
{

    /**
     * Object used to output text to the console.
     *
     * @var OutputInterface
     */
    public $output;

    /**
     * Array of registered migration versions
     *
     * @var array
     */
    private $versions = array();

    /**
     * @param EzcDbHandler $conn
     * @param OutputInterface $output
     */
    public function __construct(EzcDbHandler $conn, OutputInterface $output)
    {
        $this->connection = $conn;
        $this->output = $output;
    }

    /**
     * Return the connection handler
     *
     * @return \ezcDbHandler
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Return the array of registered versions
     *
     * @return array
     */
    public function getVersions()
    {
        return $this->versions;
    }

    /**
     * Override the registered versions array.
     *
     * Used for testing.
     *
     * @param array $versions
     */
    public function setVersions($versions)
    {
        $this->versions = $versions;
    }

    /**
     * Register versions from a list of paths.
     *
     * @param array $paths
     * @return array
     */
    public function registerVersionFromDirectories(Array $paths)
    {
        foreach ($paths as $bundle => $bundlePaths) {
            foreach ($bundlePaths as $path) {
                $path = realpath($path);
                $path = rtrim($path, '/');
                $phpFiles = glob($path . '/*.php');
                $ymlFiles = glob($path . '/*.yml');
                $sqlFiles = glob($path . '/*.sql');

                $files = array_merge($phpFiles, $ymlFiles, $sqlFiles);

                foreach ($files as $file) {
                    $this->registerVersion($file, $bundle);
                }
            }
        }

        return $this->versions;
    }

    /**
     * Register one version.
     *
     * Can handle PHP and Yaml based migration version files.
     *
     * @param string $filePath Path to the version file
     * @param string $bundle
     * @return object
     */
    public function registerVersion($filePath, $bundle)
    {

        $info = pathinfo($filePath);
        $version = substr($info['filename'], 0, 14);

        $fileNameParts = explode('_', $info['filename']);

        // Just use the file extension to define the type of the migration version definition.
        $type = $info['extension'];

        $this->checkDuplicateVersion($bundle, $version);

        $versionObject = new Version($this, $version);

        switch ($type) {
            case "php":
                //Need to require the file as autoload will not be able to find it.
                require_once $filePath;
                $class = "\\{$this->versionDirectory}\\{$bundle}\\" . $info['filename'];
                $versionObject->migration = new $class();
                $versionObject->type = 'PHP';

                array_shift( $fileNameParts );
                $versionObject->description = implode( ' ', $fileNameParts);

                $this->versions[$bundle][$version] = $versionObject;
                break;
            case "yml":
                $migrationObject = new YamlDefinitionParser();
                $migrationObject->yamlFile = $filePath;

                $versionObject->migration = $migrationObject;
                $versionObject->type = 'Yaml';

                array_shift( $fileNameParts );
                $versionObject->description = implode( ' ', $fileNameParts);

                $this->versions[$bundle][$version] = $versionObject;
                break;
            case "sql":
                $migrationObject = new SQLDefinitionParser();
                $migrationObject->sqlFile = $filePath;

                // Check if db type can be determined, skip if not
                if (!$migrationObject->isValidMigration()) {
                    $this->output->writeln("<error>The {$filePath} migration file is invalid. Skipping...</error>");
                    break;
                }

                // Check if migration is for the current db server, skip if not.
                if (!$migrationObject->isMigrationForDBServer($this->connection)) {
                    $this->output->writeln("<error>\n\n  The {$filePath} migration file is not for the current DB server ({$this->connection->getName()}). Skipping...\n</error>");
                    break;
                }

                $versionObject->migration = $migrationObject;
                $versionObject->type = "SQL";

                array_shift( $fileNameParts );
                array_shift( $fileNameParts );
                $versionObject->description = implode( ' ', $fileNameParts);

                $this->versions[$bundle][$version] = $versionObject;
                break;

            default:
                /// @todo throw an 'unsupported migration' error
        }

        if (array_key_exists($bundle, $this->versions) && is_array($this->versions[$bundle])) {
            ksort($this->versions[$bundle]);
        }

        return $version;
    }

    /**
     * Checks if a version with the same $version and $bundle has already been registered.
     *
     * @TODO: Add new exception type
     *
     * @param $bundle
     * @param $version
     * @throws \Exception
     */
    public function checkDuplicateVersion($bundle, $version)
    {

        if (isset($this->versions[$bundle][$version])) {
            throw new \Exception("Duplicate found for version {$version} in bundle {$bundle}");
        }
    }


    /**
     * Return the migrated versions grouped by bundle.
     *
     * The list is based on the values in the version table in the database.
     *
     * @return array
     */
    public function getMigratedVersions()
    {
        $this->createVersionTableIfNeeded();

        /** @var $q \ezcQuerySelect */
        $q = $this->connection->createSelectQuery();
        $q->select('bundle, version')->from($this->versionTableName);

        $stmt = $q->prepare();
        $stmt->execute();

        $results = $stmt->fetchAll();

        $bundleVersions = array();
        foreach ($results as $result) {
            $bundleVersions[$result['bundle']][] = $result['version'];
        }

        return $bundleVersions;
    }

    /**
     * Method to get the current version for a specific bundle
     *
     * @param string $bundle The bundle we want the version for
     * @return int The version or 0 if no version can be found
     */
    public function getCurrentVersionByBundle($bundle)
    {
        $this->createVersionTableIfNeeded();

        /** @var $q \ezcQuerySelect */
        $q = $this->connection->createSelectQuery();
        $q->select('version')
            ->from($this->versionTableName);

        if ($this->versions && $this->versions[$bundle]) {
            $migratedVersions = array_keys($this->versions[$bundle]);
            $q->where(
                $q->expr->lAnd(
                    $q->expr->eq('bundle', $bundle),
                    $q->expr->in($q->bindValue(implode(',', $migratedVersions)))
                )
            );
        } else {
            $q->where(
                $q->expr->eq('bundle', $q->bindValue($bundle))
            );
        }

        $q->limit(1, 0)
            ->orderBy('version', \ezcQuerySelect::DESC);

        $stmt = $q->prepare();
        $stmt->execute();
        $result = $stmt->fetchColumn(0);

        return $result !== false ? (int)$result : '0';
    }

    /**
     * Method to get the current version of all bundles we have registered migrations for
     *
     * The list is grouped by bundles.
     *
     * @todo Optimise this as it might do a lot of SQL calls
     * @return array
     */
    public function getCurrentBundleVersions()
    {
        $currentVersions = array();

        if ($this->versions) {
            $bundles = array_keys($this->versions);
            foreach ($bundles as $bundle) {
                $currentVersions[$bundle] = $this->getCurrentVersionByBundle($bundle);
            }
        }

        return $currentVersions;
    }

    /**
     * Get all the versions that have been migrated already for a specific bundle.
     *
     * @todo escape parameters in the SQL query
     * @param string $bundle
     * @return array
     */
    public function getMigratedVersionsByBundle($bundle)
    {
        $this->createVersionTableIfNeeded();

        /** @var $q \ezcQuerySelect */
        $q = $this->connection->createSelectQuery();
        $q->select('version')
            ->from($this->versionTableName)
            ->where(
                $q->expr->eq('bundle', $q->bindValue($bundle))
            );

        $stmt = $q->prepare();
        $stmt->execute();
        $results = $stmt->fetchAll();

        $versions = array();

        foreach ($results as $version) {
            $versions[] = current($version);
        }

        return $versions;
    }

    /**
     * Method to get all the migrations to be executed
     *
     * @return array An array of migrations to be executed grouped by bundles
     */
    public function migrationsToExecute()
    {
        $versions = array();

        $migratedVersions = $this->getMigratedVersions();
        $bundles = array_keys($this->versions);

        foreach ($bundles as $bundle) {
            if (array_key_exists($bundle, $this->versions)) {
                $versionsToMigrate = array();
                foreach ($this->versions[$bundle] as $versionNumber => $versionClass) {
                    if ((array_key_exists($bundle, $migratedVersions)
                            && !in_array($versionNumber, $migratedVersions[$bundle]))
                        || !array_key_exists($bundle, $migratedVersions)
                    ) {
                        $versionsToMigrate[$versionNumber] = $versionClass;
                    }
                }

                if ($versionsToMigrate) {
                    $versions[$bundle] = $versionsToMigrate;
                }
            }
        }

        return $versions;
    }

    /**
     * Inject in the service container into all of the available ContainerAware Versions
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function injectContainerIntoMigrations(ContainerInterface $container)
    {
        foreach ($this->versions as $bundle => $versions) {
            foreach ($versions as $version) {
                /** @var $version \Kaliop\eZMigrationBundle\Core\Version */
                if ($version->migration instanceof ContainerAwareInterface) {
                    $version->migration->setContainer($container);
                }
            }
        }
    }

    /**
     * Inject the bundle object into all of the available BundleAware migrations.
     *
     * @param \Symfony\Component\HttpKernel\KernelInterface $kernel
     */
    public function injectBundleIntoMigration(KernelInterface $kernel)
    {
        foreach ($this->versions as $bundle => $versions) {
            $bundleObject = $kernel->getBundle($bundle);

            foreach ($versions as $version) {
                /** @var $version \Kaliop\eZMigrationBundle\Core\Version */
                if ($version->migration instanceof BundleAwareInterface) {
                    $version->migration->setBundle($bundleObject);
                }
            }
        }
    }

}

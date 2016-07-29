<?php

namespace Kaliop\eZMigrationBundle\Core;

use eZ\Bundle\EzPublishCoreBundle\Console\Application;
use Kaliop\eZMigrationBundle\Core\DefinitionHandlers\SQLDefinitionHandler;
use Kaliop\eZMigrationBundle\Core\DefinitionHandlers\YamlDefinitionHandler;
use Kaliop\eZMigrationBundle\Interfaces\BundleAwareInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use eZ\Publish\Core\Persistence\Legacy\EzcDbHandler;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Main configuration object holding settings for the migrations.
 *
 *
 */
class Configuration
{
    /**
     * EzcDbHandler
     *
     * The database connection.
     *
     * @var \ezcDbHandler
     */
    protected $connection;

    /**
     * Flag to indicate that the migration version table has been created
     *
     * @var boolean
     */
    private $versionTableExists = false;

    /**
     * Name of the database table where installed migration versions are tracked.
     * @var string
     *
     * @todo add setter/getter, as we need to clear versionTableExists when switching this
     */
    public $versionTableName = 'kaliop_versions';

    /**
     * Name of the directory where migration versions are located
     * @var string
     */
    public $versionDirectory = 'MigrationVersions';

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
                $migrationObject = new YamlDefinitionHandler();
                $migrationObject->yamlFile = $filePath;

                $versionObject->migration = $migrationObject;
                $versionObject->type = 'Yaml';

                array_shift( $fileNameParts );
                $versionObject->description = implode( ' ', $fileNameParts);

                $this->versions[$bundle][$version] = $versionObject;
                break;
            case "sql":
                $migrationObject = new SQLDefinitionHandler();
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
     * Check if the version db table exists and create it if not.
     *
     * @return bool true if table has been created, false if it was already there
     *
     * @todo add a 'force' flag to force table re-creation
     */
    public function createVersionTableIfNeeded()
    {
        if ($this->versionTableExists) {
            return false;
        }

        if ($this->tablesExist($this->versionTableName)) {
            $this->versionTableExists = true;
            return false;
        }

        // TODO: Make this table creation not MySQL dependant
        $sql = "CREATE TABLE " .  $this->versionTableName . " (
version varchar(255) NOT NULL,
bundle varchar(255) NOT NULL,
PRIMARY KEY (version, bundle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

        $this->connection->exec($sql);

        $this->versionTableExists = true;
        return true;
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
     * Mark a version as migrated in the database
     *
     * @param string $bundle
     * @param int $version
     */
    public function markVersionMigrated($bundle, $version)
    {
        $this->createVersionTableIfNeeded();

        /** @var $q \ezcQueryInsert */
        $q = $this->connection->createInsertQuery();
        $q->insertInto($this->versionTableName)
            ->set('bundle', $q->bindValue($bundle))
            ->set('version', $q->bindValue($version));

        $stmt = $q->prepare();
        $stmt->execute();
    }

    /**
     * Mark a version as NOT migrated in the database
     *
     * @param string $bundle
     * @param int $version
     */
    public function markVersionNotMigrated($bundle, $version)
    {
        $this->createVersionTableIfNeeded();

        /** @var $q \ezcQueryDelete */
        $q = $this->connection->createDeleteQuery();
        $q->deleteFrom($this->versionTableName)
            ->where(
                $q->expr->lAnd(
                    $q->expr->eq('bundle', $q->bindValue($bundle)),
                    $q->expr->eq('version', $q->bindValue($version))
                )
            );

        $stmt = $q->prepare();
        $stmt->execute();
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

    /**
     * Helper function to check if tables with $tableNames exist in the database
     *
     * @param array|string $tableNames
     * @return bool
     */
    private function tablesExist($tableNames)
    {
        $tableNames = array_map('strtolower', (array)$tableNames);

        return count($tableNames) == count(
            \array_intersect($tableNames, array_map('strtolower', $this->listTableNames()))
        );
    }

    /**
     * Helper function to get all the table names from the database
     *
     * @todo Make this DB independent
     * @return array
     */
    private function listTableNames()
    {

        $results = array();

        $stmt = $this->connection->prepare("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $stmt->execute();

        $tables = $stmt->fetchAll();

        foreach ($tables as $table) {
            $results[] = array_shift($table);
        }

        return array_values($results);
    }
}

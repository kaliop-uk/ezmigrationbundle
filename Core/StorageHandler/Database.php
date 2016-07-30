<?php

namespace Kaliop\eZMigrationBundle\Core\StorageHandler;

use Kaliop\eZMigrationBundle\API\StorageHandlerInterface;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;

/**
 * Database-backed storage for info on executed migrations
 */
class Database implements StorageHandlerInterface
{
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
    private $versionTableName;

    /**
     * @var DatabaseHandler $connection
     */
    protected $connection;

    /**
     * @param DatabaseHandler $connection
     * @param string $versionTableName
     */
    public function __construct(DatabaseHandler $connection, $versionTableName = 'kaliop_versions')
    {
        $this->connection = $connection;
        $this->versionTableName = $versionTableName;
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
     * Return the migrated versions grouped by bundle.
     *
     * The list is based on the values in the version table in the database.
     *
     * @return array key: bundle name,
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
execution_date integer NOT_NULL,
status integer NOT_NULL
PRIMARY KEY (version, bundle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

        $this->connection->exec($sql);

        $this->versionTableExists = true;
        return true;
    }

    /**
     * Helper function to check if tables with $tableNames exist in the database
     *
     * @param array|string $tableNames
     * @return bool
     */
    protected function tablesExist($tableNames)
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
    protected function listTableNames()
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
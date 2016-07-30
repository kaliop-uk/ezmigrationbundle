<?php

namespace Kaliop\eZMigrationBundle\Core\StorageHandler;

use Kaliop\eZMigrationBundle\API\StorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\Collection\MigrationCollection;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use Doctrine\DBAL\Schema\Schema;

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
    private $migrationsTableExists = false;

    /**
     * Name of the database table where installed migration versions are tracked.
     * @var string
     *
     * @todo add setter/getter, as we need to clear versionTableExists when switching this
     */
    private $migrationsTableName;

    /**
     * @var DatabaseHandler $connection
     */
    protected $connection;

    /**
     * @param DatabaseHandler $connection
     * @param string $migrationsTableName
     */
    public function __construct(DatabaseHandler $connection, $migrationsTableName = 'kaliop_migrations')
    {
        $this->connection = $connection;
        $this->migrationsTableName = $migrationsTableName;
    }

    public function loadMigrations()
    {
        $this->createMigrationsTableIfNeeded();

        /** @var \eZ\Publish\Core\Persistence\Database\SelectQuery $q */
        $q = $this->connection->createSelectQuery();
        $q->select('migration, path, execution_date, status')->from($this->migrationsTableName);

        $stmt = $q->prepare();
        $stmt->execute();

        $results = $stmt->fetchAll();

        $migrations = array();
        foreach ($results as $result) {
            $migrations[$result['migration']] = $result;
        }

        return new MigrationCollection($migrations);
    }

    /**
     * Check if the version db table exists and create it if not.
     *
     * @return bool true if table has been created, false if it was already there
     *
     * @todo add a 'force' flag to force table re-creation
     * @todo manage changes to table definition!
     */
    public function createMigrationsTableIfNeeded()
    {
        if ($this->migrationsTableExists) {
            return false;
        }

        if ($this->tableExist($this->migrationsTableName)) {
            $this->migrationsTableExists = true;
            return false;
        }

        $this->createMigrationsTable();

        $this->migrationsTableExists = true;
        return true;
    }

    public function createMigrationsTable()
    {
        /** @var \Doctrine\DBAL\Schema\AbstractSchemaManager $sm */
        $sm = $this->connection->getConnection()->getSchemaManager();
        $dbPlatform = $sm->getDatabasePlatform();

        $schema = new Schema();

        $t = $schema->createTable($this->migrationsTableName);
        $t->addColumn('migration', 'string', array('length' => 255));
        $t->addColumn('path', 'string', array('length' => 4000));
        $t->addColumn('execution_date', 'integer', array('notnull' => false));
        $t->addColumn('status', 'integer', array('default ' => 0));
        $t->setPrimaryKey(array('migration'));

        foreach($schema->toSql($dbPlatform) as $sql) {
            //$dbconn->query($sql);
            $this->connection->exec($sql);
        }
    }

    /**
     * Helper function to check if a table exists in the database
     *
     * @param string $tableName
     * @return bool
     */
    protected function tableExist($tableName)
    {
        /** @var \Doctrine\DBAL\Schema\AbstractSchemaManager $sm */
        $sm = $this->connection->getConnection()->getSchemaManager();
        foreach($sm->listTables() as $table) {
            if($table->getName() == $tableName) {
                return true;
            }
        }

        return false;
    }


/// *** BELOW THE FOLD: TO BE REFACTORED ***


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
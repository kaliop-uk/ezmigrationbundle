<?php

namespace Kaliop\eZMigrationBundle\Core\StorageHandler;

use Kaliop\eZMigrationBundle\API\StorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\Collection\MigrationCollection;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use Doctrine\DBAL\Schema\Schema;
use eZ\Publish\Core\Persistence\Database\SelectQuery;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;

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
    protected $dbHandler;

    /**
     * @param DatabaseHandler $dbHandler
     * @param string $migrationsTableName
     */
    public function __construct(DatabaseHandler $dbHandler, $migrationsTableName = 'kaliop_migrations')
    {
        $this->dbHandler = $dbHandler;
        $this->migrationsTableName = $migrationsTableName;
    }

    /**
     * @return MigrationCollection
     * @todo add support offset, limit
     */
    public function loadMigrations()
    {
        $this->createMigrationsTableIfNeeded();

        /** @var \eZ\Publish\Core\Persistence\Database\SelectQuery $q */
        $q = $this->dbHandler->createSelectQuery();
        $q->select('migration, md5, path, execution_date, status, execution_error')
            ->from($this->migrationsTableName)
            ->orderBy('migration', SelectQuery::ASC);
        $stmt = $q->prepare();
        $stmt->execute();
        $results = $stmt->fetchAll();

        $migrations = array();
        foreach ($results as $result) {
            $migrations[$result['migration']] = $this->arrayToMigration($result);
        }

        return new MigrationCollection($migrations);
    }

    /**
     * Starts a migration, given its definition: stores its status in the db, returns the Migration object
     *
     * @param MigrationDefinition $migrationDefinition
     * @return Migration
     * @throws \Exception if migration was already executing or already done
     * @todo add a parameter to allow re-execution of already-done migrations
     */
    public function startMigration(MigrationDefinition $migrationDefinition)
    {
        $this->createMigrationsTableIfNeeded();

        // select for update

        // annoyingly enough, neither Doctrine nor EZP provide built in support for 'FOR UPDATE' in their query builders...
        // at least the doctrine one allows us to still use parameter binding when we add our sql pqrticle
        $conn = $this->dbHandler->getConnection();

        $qb = $conn->createQueryBuilder();
        $qb->select('*')
            ->from($this->migrationsTableName)
            ->where('migration = ?');
        $sql = $qb->getSQL() . ' FOR UPDATE';

        $conn->beginTransaction();

        $stmt = $conn->executeQuery($sql, array($migrationDefinition->name));
        $existingMigrationData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (is_array($existingMigrationData)) {
            // migration exists

            // fail if it was already executing or already done
            if ($existingMigrationData['status'] == Migration::STATUS_STARTED) {
                // commit to release the lock
                $conn->commit();
                throw new \Exception("Migration '{$migrationDefinition->name}' can not be started as it is already executing");
            }
            if ($existingMigrationData['status'] == Migration::STATUS_DONE) {
                // commit to release the lock
                $conn->commit();
                throw new \Exception("Migration '{$migrationDefinition->name}' can not be started as it was already executed");
            }

            $migration = new Migration(
                $migrationDefinition->name,
                md5($migrationDefinition->rawDefinition),
                $migrationDefinition->path,
                time(),
                Migration::STATUS_STARTED
            );
            $conn->update(
                $this->migrationsTableName,
                array(
                    'execution_date' => $migration->executionDate,
                    'status' => Migration::STATUS_STARTED,
                    'execution_error' => null,
                ),
                array('migration' => $migrationDefinition->name)
            );
        } else {
            // migration did not exist. Create it!

            $migration = new Migration(
                $migrationDefinition->name,
                md5($migrationDefinition->rawDefinition),
                $migrationDefinition->path,
                time(),
                Migration::STATUS_STARTED
            );
            $conn->insert($this->migrationsTableName, $this->migrationToArray($migration));
        }

        $conn->commit();
        return $migration;
    }

    /**
     * Stops a migration by storing it in the db. Migration status can not be 'started'
     *
     * @param Migration $migration
     * @throws \Exception
     */
    public function endMigration(Migration $migration)
    {
        if ($migration->status == Migration::STATUS_STARTED ) {
            throw new \Exception("Migration '{$migration->name}' can not be ended as its status is 'started'...");
        }

        $this->createMigrationsTableIfNeeded();

        // select for update

        // annoyingly enough, neither Doctrine nor EZP provide built in support for 'FOR UPDATE' in their query builders...
        // at least the doctrine one allows us to still use parameter binding when we add our sql pqrticle
        $conn = $this->dbHandler->getConnection();

        $qb = $conn->createQueryBuilder();
        $qb->select('*')
            ->from($this->migrationsTableName)
            ->where('migration = ?');
        $sql = $qb->getSQL() . ' FOR UPDATE';

        $conn->beginTransaction();

        $stmt = $conn->executeQuery($sql, array($migration->name));
        $existingMigrationData = $stmt->fetch(\PDO::FETCH_ASSOC);

        // fail if it was not executing

        if (!is_array($existingMigrationData)) {
            // commit to release the lock
            $conn->commit();
            throw new \Exception("Migration '{$migration->name}' can not be ended as it is not found");
        }

        if ($existingMigrationData['status'] != Migration::STATUS_STARTED) {
            // commit to release the lock
            $conn->commit();
            throw new \Exception("Migration '{$migration->name}' can not be ended as it is not executing");
        }

        $conn->update(
            $this->migrationsTableName,
            array(
                'status' => $migration->status,
                'execution_error' => $migration->executionError,
            ),
            array('migration' => $migration->name)
        );

        $conn->commit();
    }

    /**
     * Check if the version db table exists and create it if not.
     *
     * @return bool true if table has been created, false if it was already there
     *
     * @todo add a 'force' flag to force table re-creation
     * @todo manage changes to table definition
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
        $sm = $this->dbHandler->getConnection()->getSchemaManager();
        $dbPlatform = $sm->getDatabasePlatform();

        $schema = new Schema();

        $t = $schema->createTable($this->migrationsTableName);
        $t->addColumn('migration', 'string', array('length' => 255));
        $t->addColumn('path', 'string', array('length' => 4000));
        $t->addColumn('md5', 'string', array('length' => 32));
        $t->addColumn('execution_date', 'integer', array('notnull' => false));
        $t->addColumn('status', 'integer', array('default ' => Migration::STATUS_TODO));
        $t->addColumn('execution_error', 'string', array('length' => 4000, 'notnull' => false));
        $t->setPrimaryKey(array('migration'));

        foreach($schema->toSql($dbPlatform) as $sql) {
            $this->dbHandler->exec($sql);
        }
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $tableName
     * @return bool
     */
    protected function tableExist($tableName)
    {
        /** @var \Doctrine\DBAL\Schema\AbstractSchemaManager $sm */
        $sm = $this->dbHandler->getConnection()->getSchemaManager();
        foreach($sm->listTables() as $table) {
            if($table->getName() == $tableName) {
                return true;
            }
        }

        return false;
    }

    protected function migrationToArray(Migration $migration)
    {
        return array(
            'migration' => $migration->name,
            'md5' => $migration->md5,
            'path' => $migration->path,
            'execution_date' => $migration->executionDate,
            'status' => $migration->status,
            'execution_error' => $migration->executionError
        );
    }

    protected function arrayToMigration(array $data)
    {
        return new Migration(
            $data['migration'],
            $data['md5'],
            $data['path'],
            $data['execution_date'],
            $data['status'],
            $data['execution_error']
        );
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
        $q = $this->dbHandler->createInsertQuery();
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
        $q = $this->dbHandler->createDeleteQuery();
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
        $q = $this->dbHandler->createSelectQuery();
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
        $q = $this->dbHandler->createSelectQuery();
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

        $stmt = $this->dbHandler->prepare("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $stmt->execute();

        $tables = $stmt->fetchAll();

        foreach ($tables as $table) {
            $results[] = array_shift($table);
        }

        return array_values($results);
    }
}
<?php

namespace Kaliop\eZMigrationBundle\Core\StorageHandler;

use Kaliop\eZMigrationBundle\API\StorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\Collection\MigrationCollection;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use Doctrine\DBAL\Schema\Schema;
use eZ\Publish\Core\Persistence\Database\SelectQuery;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Database-backed storage for info on executed migrations
 *
 * @todo replace all usage of the ezcdb api with the doctrine dbal one, so that we only depend on one
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
     * @param string $migrationName
     * @return Migration|null
     */
    public function loadMigration($migrationName)
    {
        /** @var \eZ\Publish\Core\Persistence\Database\SelectQuery $q */
        $q = $this->dbHandler->createSelectQuery();
        $q->select('migration, md5, path, execution_date, status, execution_error')
            ->from($this->migrationsTableName)
            ->where($q->expr->eq('migration', $q->bindValue($migrationName)));
        $stmt = $q->prepare();
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (is_array($result) && !empty($result)) {
            return $this->arrayToMigration($result);
        }

        return null;
    }

    /**
     * Creates and stores a new migration (leaving it in TODO status)
     * @param MigrationDefinition $migrationDefinition
     * @return mixed
     * @throws \Exception If the migration exists already (we rely on the PK for that)
     */
    public function addMigration(MigrationDefinition $migrationDefinition)
    {
        $this->createMigrationsTableIfNeeded();

        $conn = $this->dbHandler->getConnection();

        $migration = new Migration(
            $migrationDefinition->name,
            md5($migrationDefinition->rawDefinition),
            $migrationDefinition->path,
            null,
            Migration::STATUS_TODO
        );
        try {
            $conn->insert($this->migrationsTableName, $this->migrationToArray($migration));
        } catch(UniqueConstraintViolationException $e) {
            throw new \Exception("Migration '{$migrationDefinition->name}' already exists");
        }

        return $migration;
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
            ->from($this->migrationsTableName, 'm')
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
        if ($migration->status == Migration::STATUS_STARTED) {
            throw new \Exception("Migration '{$migration->name}' can not be ended as its status is 'started'...");
        }

        $this->createMigrationsTableIfNeeded();

        // select for update

        // annoyingly enough, neither Doctrine nor EZP provide built in support for 'FOR UPDATE' in their query builders...
        // at least the doctrine one allows us to still use parameter binding when we add our sql pqrticle
        $conn = $this->dbHandler->getConnection();

        $qb = $conn->createQueryBuilder();
        $qb->select('*')
            ->from($this->migrationsTableName, 'm')
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
                'execution_date' => $migration->executionDate
            ),
            array('migration' => $migration->name)
        );

        $conn->commit();
    }

    /**
     * Removes a Migration from the table
     * @param Migration $migration
     */
    public function deleteMigration(Migration $migration)
    {
        $this->createMigrationsTableIfNeeded();
        $conn = $this->dbHandler->getConnection();
        $conn->delete($this->migrationsTableName, array('migration' => $migration->name));
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
        // in case users want to look up migrations by their full path
        // NB: disabled for the moment, as it causes problems on some versions of mysql which limit index length to 767 bytes,
        // and 767 bytes can be either 255 chars or 191 chars depending on charset utf8 or utf8mb4...
        //$t->addIndex(array('path'));

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
            if ($table->getName() == $tableName) {
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
}
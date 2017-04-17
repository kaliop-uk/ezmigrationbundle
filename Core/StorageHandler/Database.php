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
     * Flag to indicate that the migration table has been created
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
    protected $migrationsTableName;

    /**
     * @var DatabaseHandler $connection
     */
    protected $dbHandler;

    protected $fieldList = 'migration, md5, path, execution_date, status, execution_error';
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
     * @param int $limit
     * @param int $offset
     * @return MigrationCollection
     */
    public function loadMigrations($limit = null, $offset = null)
    {
        return $this->loadMigrationsInner(null, $limit, $offset);
    }

    /**
     * @param int $status
     * @param int $limit
     * @param int $offset
     * @return MigrationCollection
     */
    public function loadMigrationsByStatus($status, $limit = null, $offset = null)
    {
        return $this->loadMigrationsInner($status, $limit, $offset);
    }

    /**
     * @param int $status
     * @param int $limit
     * @param int $offset
     * @return MigrationCollection
     */
    protected function loadMigrationsInner($status = null, $limit = null, $offset = null)
    {
        $this->createMigrationsTableIfNeeded();

        /** @var \eZ\Publish\Core\Persistence\Database\SelectQuery $q */
        $q = $this->dbHandler->createSelectQuery();
        $q->select($this->fieldList)
            ->from($this->migrationsTableName)
            ->orderBy('migration', SelectQuery::ASC);
        if ($status != null) {
            $q->where($q->expr->eq('status', $q->bindValue($status)));
        }
        if ($limit > 0 || $offset > 0) {
            if ($limit <= 0) {
                $limit = null;
            }
            if ($offset == 0) {
                $offset = null;
            }
            $q->limit($limit, $offset);
        }
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
        $this->createMigrationsTableIfNeeded();

        /** @var \eZ\Publish\Core\Persistence\Database\SelectQuery $q */
        $q = $this->dbHandler->createSelectQuery();
        $q->select($this->fieldList)
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
     * @return Migration
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
        } catch (UniqueConstraintViolationException $e) {
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
        return $this->createMigration($migrationDefinition, Migration::STATUS_STARTED, 'started');
    }

    /**
     * Stops a migration by storing it in the db. Migration status can not be 'started'
     *
     * NB: if this call happens within another DB transaction which has already been flagged for rollback, the result
     * will be that a RuntimeException is thrown, as Doctrine does not allow to call commit() after rollback().
     * One way to fix the problem would be not to use a transaction and select-for-update here, but since that is the
     * best way to insure atomic updates, I am loath to remove it.
     * A known workaround is to call the Doctrine Connection method setNestTransactionsWithSavepoints(true); this can
     * be achieved as simply as setting the parameter 'use_savepoints' in the doctrine connection configuration.
     *
     * @param Migration $migration
     * @param bool $force When true, the migration will be updated even if it was not in 'started' status
     * @throws \Exception If the migration was not started (unless $force=true)
     */
    public function endMigration(Migration $migration, $force = false)
    {
        if ($migration->status == Migration::STATUS_STARTED) {
            throw new \Exception("Migration '{$migration->name}' can not be ended as its status is 'started'...");
        }

        $this->createMigrationsTableIfNeeded();

        // select for update

        // annoyingly enough, neither Doctrine nor EZP provide built in support for 'FOR UPDATE' in their query builders...
        // at least the doctrine one allows us to still use parameter binding when we add our sql particle
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

        if (($existingMigrationData['status'] != Migration::STATUS_STARTED) && !$force) {
            // commit to release the lock
            $conn->commit();
            throw new \Exception("Migration '{$migration->name}' can not be ended as it is not executing");
        }

        $conn->update(
            $this->migrationsTableName,
            array(
                'status' => $migration->status,
                /// @todo use mb_substr (if all dbs we support count col length not in bytes but in chars...)
                'execution_error' => substr($migration->executionError, 0, 4000),
                'execution_date' => $migration->executionDate
            ),
            array('migration' => $migration->name)
        );

        $conn->commit();
    }

    /**
     * Removes a Migration from the table - regardless of its state!
     *
     * @param Migration $migration
     */
    public function deleteMigration(Migration $migration)
    {
        $this->createMigrationsTableIfNeeded();
        $conn = $this->dbHandler->getConnection();
        $conn->delete($this->migrationsTableName, array('migration' => $migration->name));
    }

    /**
     * Skips a migration by storing it in the db. Migration status can not be 'started'
     *
     * @param MigrationDefinition $migrationDefinition
     * @return Migration
     * @throws \Exception If the migration was already executed or executing
     */
    public function skipMigration(MigrationDefinition $migrationDefinition)
    {
        return $this->createMigration($migrationDefinition, Migration::STATUS_SKIPPED, 'skipped');
    }

    /**
     * @param MigrationDefinition $migrationDefinition
     * @param int $status
     * @param string $action
     * @return Migration
     * @throws \Exception
     */
    protected function createMigration(MigrationDefinition $migrationDefinition, $status, $action)
    {
        $this->createMigrationsTableIfNeeded();

        // select for update

        // annoyingly enough, neither Doctrine nor EZP provide built in support for 'FOR UPDATE' in their query builders...
        // at least the doctrine one allows us to still use parameter binding when we add our sql particle
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
                throw new \Exception("Migration '{$migrationDefinition->name}' can not be $action as it is already executing");
            }
            if ($existingMigrationData['status'] == Migration::STATUS_DONE) {
                // commit to release the lock
                $conn->commit();
                throw new \Exception("Migration '{$migrationDefinition->name}' can not be $action as it was already executed");
            }
            if ($existingMigrationData['status'] == Migration::STATUS_SKIPPED) {
                // commit to release the lock
                $conn->commit();
                throw new \Exception("Migration '{$migrationDefinition->name}' can not be $action as it was already skipped");
            }

            // do not set migration start date if we are skipping it
            $migration = new Migration(
                $migrationDefinition->name,
                md5($migrationDefinition->rawDefinition),
                $migrationDefinition->path,
                ($status == Migration::STATUS_SKIPPED ? null : time()),
                $status
            );
            $conn->update(
                $this->migrationsTableName,
                array(
                    'execution_date' => $migration->executionDate,
                    'status' => $status,
                    'execution_error' => null
                ),
                array('migration' => $migrationDefinition->name)
            );
            $conn->commit();

        } else {
            // migration did not exist. Create it!

            // commit immediately, to release the lock and avoid deadlocks
            $conn->commit();

            $migration = new Migration(
                $migrationDefinition->name,
                md5($migrationDefinition->rawDefinition),
                $migrationDefinition->path,
                ($status == Migration::STATUS_SKIPPED ? null : time()),
                $status
            );
            $conn->insert($this->migrationsTableName, $this->migrationToArray($migration));
        }

        return $migration;
    }

    /**
     * Removes all migration from storage (regardless of their status)
     */
    public function deleteMigrations()
    {
        if ($this->tableExist($this->migrationsTableName)) {
            $this->dbHandler->exec('DROP TABLE ' . $this->migrationsTableName);
        }
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

        foreach ($schema->toSql($dbPlatform) as $sql) {
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
        foreach ($sm->listTables() as $table) {
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

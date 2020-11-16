<?php

namespace Kaliop\eZMigrationBundle\Core\StorageHandler\Database;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use eZ\Publish\Core\Persistence\Database\QueryException;
use eZ\Publish\Core\Persistence\Database\SelectQuery;
use Kaliop\eZMigrationBundle\API\StorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\Collection\MigrationCollection;
use Kaliop\eZMigrationBundle\API\Value\Migration as APIMigration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;

use Kaliop\eZMigrationBundle\API\ConfigResolverInterface;

/**
 * Database-backed storage for info on executed migrations
 *
 * @todo replace all usage of the ezcdb api with the doctrine dbal one, so that we only depend on one
 */
class Migration extends TableStorage implements StorageHandlerInterface
{
    protected $fieldList = 'migration, md5, path, execution_date, status, execution_error';

    /**
     * @param DatabaseHandler $dbHandler
     * @param string $tableNameParameter
     * @param ConfigResolverInterface $configResolver
     * @param array $tableCreationOptions
     * @throws \Exception
     */
    public function __construct(DatabaseHandler $dbHandler, $tableNameParameter = 'kaliop_migrations', ConfigResolverInterface $configResolver = null, $tableCreationOptions = array())
    {
        parent::__construct($dbHandler, $tableNameParameter, $configResolver, $tableCreationOptions);
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return MigrationCollection
     */
    public function loadMigrations($limit = null, $offset = null)
    {
        return $this->loadMigrationsInner(null, null, $limit, $offset);
    }

    /**
     * @param int $status
     * @param int $limit
     * @param int $offset
     * @return MigrationCollection
     */
    public function loadMigrationsByStatus($status, $limit = null, $offset = null)
    {
        return $this->loadMigrationsInner($status, null, $limit, $offset);
    }

    public function loadMigrationsByPaths($paths, $limit = null, $offset = null)
    {
        return $this->loadMigrationsInner(null, $paths, $limit, $offset);
    }

    /**
     * @param int $status
     * @param null|string[] $paths
     * @param int $limit
     * @param int $offset
     * @return MigrationCollection
     */
    protected function loadMigrationsInner($status = null, $paths = array(), $limit = null, $offset = null)
    {
        $this->createTableIfNeeded();

        /** @var \eZ\Publish\Core\Persistence\Database\SelectQuery $q */
        $q = $this->dbHandler->createSelectQuery();
        $q->select($this->fieldList)
            ->from($this->tableName)
            ->orderBy('migration', SelectQuery::ASC);
        if ($status !== null || (is_array($paths) && count($paths))) {
            $exps = [];
            if ($status !== null) {
                $exps[] = $q->expr->eq('status', $q->bindValue($status));
            }
            if (is_array($paths) && count($paths)) {
                $pexps = array();
                foreach($paths as $path) {
                    /// @todo use a proper db-aware escaping function
                    $pexps[] = $q->expr->like('path', "'" . str_replace(array('_', '%', "'"), array('\_', '\%', "''"), $path).'%' . "'");
                }
                $exps[] = $q->expr->lor($pexps);
            }
            $q->where($q->expr->land($exps));
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
     * @return APIMigration|null
     */
    public function loadMigration($migrationName)
    {
        $this->createTableIfNeeded();

        /** @var \eZ\Publish\Core\Persistence\Database\SelectQuery $q */
        $q = $this->dbHandler->createSelectQuery();
        $q->select($this->fieldList)
            ->from($this->tableName)
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
     * @return APIMigration
     * @throws \Exception If the migration exists already (we rely on the PK for that)
     */
    public function addMigration(MigrationDefinition $migrationDefinition)
    {
        $this->createTableIfNeeded();

        $conn = $this->getConnection();

        $migration = new APIMigration(
            $migrationDefinition->name,
            md5($migrationDefinition->rawDefinition),
            $migrationDefinition->path,
            null,
            APIMigration::STATUS_TODO
        );
        try {
            $conn->insert($this->tableName, $this->migrationToArray($migration));
        } catch (UniqueConstraintViolationException $e) {
            throw new \Exception("Migration '{$migrationDefinition->name}' already exists");
        }

        return $migration;
    }

    /**
     * Starts a migration, given its definition: stores its status in the db, returns the Migration object
     *
     * @param MigrationDefinition $migrationDefinition
     * @param bool $force when true, starts migrations even if they already exist in DONE, SKIPPED status
     * @return APIMigration
     * @throws \Exception if migration was already executing or already done
     * @todo add a parameter to allow re-execution of already-done migrations
     */
    public function startMigration(MigrationDefinition $migrationDefinition, $force = false)
    {
        return $this->createMigration($migrationDefinition, APIMigration::STATUS_STARTED, 'started', $force);
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
     * @param APIMigration $migration
     * @param bool $force When true, the migration will be updated even if it was not in 'started' status
     * @throws \Exception If the migration was not started (unless $force=true)
     */
    public function endMigration(APIMigration $migration, $force = false)
    {
        if ($migration->status == APIMigration::STATUS_STARTED) {
            throw new \Exception($this->getEntityName($migration)." '{$migration->name}' can not be ended as its status is 'started'...");
        }

        $this->createTableIfNeeded();

        // select for update

        // annoyingly enough, neither Doctrine nor EZP provide built in support for 'FOR UPDATE' in their query builders...
        // at least the doctrine one allows us to still use parameter binding when we add our sql particle
        $conn = $this->getConnection();

        $qb = $conn->createQueryBuilder();
        $qb->select('*')
            ->from($this->tableName, 'm')
            ->where('migration = ?');
        $sql = $qb->getSQL() . ' FOR UPDATE';

        $conn->beginTransaction();

        $stmt = $conn->executeQuery($sql, array($migration->name));
        $existingMigrationData = $stmt->fetch(\PDO::FETCH_ASSOC);

        // fail if it was not executing

        if (!is_array($existingMigrationData)) {
            // commit to release the lock
            $conn->commit();
            throw new \Exception($this->getEntityName($migration)." '{$migration->name}' can not be ended as it is not found");
        }

        if (($existingMigrationData['status'] != APIMigration::STATUS_STARTED) && !$force) {
            // commit to release the lock
            $conn->commit();
            throw new \Exception($this->getEntityName($migration)." '{$migration->name}' can not be ended as it is not executing");
        }

        $conn->update(
            $this->tableName,
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
     * @param APIMigration $migration
     */
    public function deleteMigration(APIMigration $migration)
    {
        $this->createTableIfNeeded();
        $conn = $this->getConnection();
        $conn->delete($this->tableName, array('migration' => $migration->name));
    }

    /**
     * Skips a migration by storing it in the db. Migration status can not be 'started'
     *
     * @param MigrationDefinition $migrationDefinition
     * @return APIMigration
     * @throws \Exception If the migration was already executed or executing
     */
    public function skipMigration(MigrationDefinition $migrationDefinition)
    {
        return $this->createMigration($migrationDefinition, APIMigration::STATUS_SKIPPED, 'skipped');
    }

    /**
     * @param MigrationDefinition $migrationDefinition
     * @param int $status
     * @param string $action
     * @param bool $force
     * @return APIMigration
     * @throws \Exception
     */
    protected function createMigration(MigrationDefinition $migrationDefinition, $status, $action, $force = false)
    {
        $this->createTableIfNeeded();

        // select for update

        // annoyingly enough, neither Doctrine nor EZP provide built in support for 'FOR UPDATE' in their query builders...
        // at least the doctrine one allows us to still use parameter binding when we add our sql particle
        $conn = $this->getConnection();

        $qb = $conn->createQueryBuilder();
        $qb->select('*')
            ->from($this->tableName, 'm')
            ->where('migration = ?');
        $sql = $qb->getSQL() . ' FOR UPDATE';

        $conn->beginTransaction();

        $stmt = $conn->executeQuery($sql, array($migrationDefinition->name));
        $existingMigrationData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (is_array($existingMigrationData)) {
            // migration exists

            // fail if it was already executing
            if ($existingMigrationData['status'] == APIMigration::STATUS_STARTED) {
                // commit to release the lock
                $conn->commit();
                throw new \Exception("Migration '{$migrationDefinition->name}' can not be $action as it is already executing");
            }
            // fail if it was already already done, unless in 'force' mode
            if (!$force) {
                if ($existingMigrationData['status'] == APIMigration::STATUS_DONE) {
                    // commit to release the lock
                    $conn->commit();
                    throw new \Exception("Migration '{$migrationDefinition->name}' can not be $action as it was already executed");
                }
                if ($existingMigrationData['status'] == APIMigration::STATUS_SKIPPED) {
                    // commit to release the lock
                    $conn->commit();
                    throw new \Exception("Migration '{$migrationDefinition->name}' can not be $action as it was already skipped");
                }
            }

            // do not set migration start date if we are skipping it
            $migration = new APIMigration(
                $migrationDefinition->name,
                md5($migrationDefinition->rawDefinition),
                $migrationDefinition->path,
                ($status == APIMigration::STATUS_SKIPPED ? null : time()),
                $status
            );
            $conn->update(
                $this->tableName,
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

            $migration = new APIMigration(
                $migrationDefinition->name,
                md5($migrationDefinition->rawDefinition),
                $migrationDefinition->path,
                ($status == APIMigration::STATUS_SKIPPED ? null : time()),
                $status
            );
            $conn->insert($this->tableName, $this->migrationToArray($migration));
        }

        return $migration;
    }

    public function resumeMigration(APIMigration $migration)
    {
        $this->createTableIfNeeded();

        // select for update

        // annoyingly enough, neither Doctrine nor EZP provide built in support for 'FOR UPDATE' in their query builders...
        // at least the doctrine one allows us to still use parameter binding when we add our sql particle
        $conn = $this->getConnection();

        $qb = $conn->createQueryBuilder();
        $qb->select('*')
            ->from($this->tableName, 'm')
            ->where('migration = ?');
        $sql = $qb->getSQL() . ' FOR UPDATE';

        $conn->beginTransaction();

        $stmt = $conn->executeQuery($sql, array($migration->name));
        $existingMigrationData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($existingMigrationData)) {
            // commit immediately, to release the lock and avoid deadlocks
            $conn->commit();
            throw new \Exception($this->getEntityName($migration)." '{$migration->name}' can not be resumed as it is not found");
        }

        // migration exists

        // fail if it was not suspended
        if ($existingMigrationData['status'] != APIMigration::STATUS_SUSPENDED) {
            // commit to release the lock
            $conn->commit();
            throw new \Exception($this->getEntityName($migration)." '{$migration->name}' can not be resumed as it is not suspended");
        }

        $migration = new APIMigration(
            $migration->name,
            $migration->md5,
            $migration->path,
            time(),
            APIMigration::STATUS_STARTED
        );

        $conn->update(
            $this->tableName,
            array(
                'execution_date' => $migration->executionDate,
                'status' => APIMigration::STATUS_STARTED,
                'execution_error' => null
            ),
            array('migration' => $migration->name)
        );
        $conn->commit();

        return $migration;
    }

    /**
     * Removes all migration from storage (regardless of their status)
     */
    public function deleteMigrations()
    {
        $this->drop();
    }

    /**
     * @throws QueryException
     */
    public function createTable()
    {
        /** @var \Doctrine\DBAL\Schema\AbstractSchemaManager $sm */
        $sm = $this->getConnection()->getSchemaManager();
        $dbPlatform = $sm->getDatabasePlatform();

        $schema = new Schema();

        $t = $schema->createTable($this->tableName);
        $t->addColumn('migration', 'string', array('length' => 255));
        $t->addColumn('path', 'string', array('length' => 4000));
        $t->addColumn('md5', 'string', array('length' => 32));
        $t->addColumn('execution_date', 'integer', array('notnull' => false));
        $t->addColumn('status', 'integer', array('default ' => APIMigration::STATUS_TODO));
        $t->addColumn('execution_error', 'string', array('length' => 4000, 'notnull' => false));
        $t->setPrimaryKey(array('migration'));
        // in case users want to look up migrations by their full path
        // NB: disabled for the moment, as it causes problems on some versions of mysql which limit index length to 767 bytes,
        // and 767 bytes can be either 255 chars or 191 chars depending on charset utf8 or utf8mb4...
        //$t->addIndex(array('path'));

        $this->injectTableCreationOptions($t);

        foreach ($schema->toSql($dbPlatform) as $sql) {
            try {
                $this->dbHandler->exec($sql);
            } catch(QueryException $e) {
                // work around limitations in both Mysql and Doctrine
                // @see https://github.com/kaliop-uk/ezmigrationbundle/issues/176
                if (strpos($e->getMessage(), '1071 Specified key was too long; max key length is 767 bytes') !== false &&
                    strpos($sql, 'PRIMARY KEY(migration)') !== false) {
                    $this->dbHandler->exec(str_replace('PRIMARY KEY(migration)', 'PRIMARY KEY(migration(191))', $sql));
                } else {
                    throw $e;
                }
            }
        }
    }

    protected function migrationToArray(APIMigration $migration)
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
        return new APIMigration(
            $data['migration'],
            $data['md5'],
            $data['path'],
            $data['execution_date'],
            $data['status'],
            $data['execution_error']
        );
    }

    protected function getEntityName($migration)
    {
        $arr = explode('\\', get_class($migration));
        return end($arr);
    }
}

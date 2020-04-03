<?php

namespace Kaliop\eZMigrationBundle\Core\StorageHandler\Database;

use Doctrine\DBAL\FetchMode;
use Kaliop\eZMigrationBundle\API\ContextStorageHandlerInterface;
use Doctrine\DBAL\Schema\Schema;

class Context extends TableStorage implements ContextStorageHandlerInterface
{
    protected $fieldList = 'migration, context, insertion_date';

    public function loadMigrationContext($migrationName)
    {
        $this->createTableIfNeeded();

        /** @var \Doctrine\DBAL\Query\QueryBuilder $q */
        $q = $this->connection->createQueryBuilder();

        $q->select($this->fieldList)
            ->from($this->tableName)
            ->where($q->expr()->eq('migration', $q->createPositionalParameter($migrationName)));

        $stmt = $q->execute();
        $result = $stmt->fetch(FetchMode::ASSOCIATIVE);

        if (is_array($result) && !empty($result)) {
            return $this->stringToContext($result['context']);
        }

        return null;
    }

    /**
     * Stores a migration context
     *
     * @param string $migrationName
     * @param array $context
     */
    public function storeMigrationContext($migrationName, array $context)
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

        $stmt = $conn->executeQuery($sql, array($migrationName));
        $existingMigrationData = $stmt->fetch(FetchMode::ASSOCIATIVE);

        if (is_array($existingMigrationData)) {
            // context exists

            $conn->update(
                $this->tableName,
                array(
                    'context' => $this->contextToString($context),
                    'insertion_date' => time(),
                ),
                array('migration' => $migrationName)
            );
            $conn->commit();

        } else {
            // context did not exist. Create it!

            // commit immediately, to release the lock and avoid deadlocks
            $conn->commit();

            $conn->insert($this->tableName, array(
                'migration' => $migrationName,
                'context' => $this->contextToString($context),
                'insertion_date' => time(),
            ));
        }
    }

    /**
     * Removes a migration context from storage
     *
     * @param string $migrationName
     */
    public function deleteMigrationContext($migrationName)
    {
        $this->createTableIfNeeded();
        $conn = $this->getConnection();
        $conn->delete($this->tableName, array('migration' => $migrationName));
    }

    /**
     * Removes all migration contexts from storage (regardless of the migration status/existence)
     */
    public function deleteMigrationContexts()
    {
        $this->truncate();
    }

    public function createTable()
    {
        /** @var \Doctrine\DBAL\Schema\AbstractSchemaManager $sm */
        $sm = $this->getConnection()->getSchemaManager();
        $dbPlatform = $sm->getDatabasePlatform();

        $schema = new Schema();

        $t = $schema->createTable($this->tableName);
        $t->addColumn('migration', 'string', array('length' => 255));
        $t->addColumn('context', 'text');
        $t->addColumn('insertion_date', 'integer');
        $t->setPrimaryKey(array('migration'));

        foreach ($schema->toSql($dbPlatform) as $sql) {
            $this->connection->exec($sql);
        }
    }

    protected function stringToContext($data)
    {
        return json_decode($data, true);
    }

    protected function contextToString(array $context)
    {
        return json_encode($context);
    }
}

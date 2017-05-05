<?php

namespace Kaliop\eZMigrationBundle\Core\StorageHandler\Database;

use Kaliop\eZMigrationBundle\API\ContextStorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;

class Context extends TableStorage implements ContextStorageHandlerInterface
{
    protected $fieldList = 'migration, context, references, insertion_date';

    public function loadMigrationContext($migrationName)
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
            return $this->arrayToContext($result);
        }

        return null;
    }

    /**
     * Stores a migration context
     *
     * @param MigrationDefinition $migrationDefinition
     * @return Migration
     * @throws \Exception If the migration exists already
     */
    public function storeMigrationContext(MigrationDefinition $migrationDefinition)
    {

    }

    /**
     * Removes a migration context from storage (regardless of the migration status)
     *
     * @param Migration $migration
     */
    public function deleteMigrationContext(Migration $migration)
    {
        $this->createTableIfNeeded();
        $conn = $this->getConnection();
        $conn->delete($this->tableName, array('migration' => $migration->name));
    }

    /**
     * Removes all migration contexts from storage (regardless of the migration status)
     */
    public function deleteMigrationContexts()
    {

    }

    public function createTable()
    {
        /** @var \Doctrine\DBAL\Schema\AbstractSchemaManager $sm */
        $sm = $this->dbHandler->getConnection()->getSchemaManager();
        $dbPlatform = $sm->getDatabasePlatform();

        $schema = new Schema();

        $t = $schema->createTable($this->tableName);
        $t->addColumn('migration', 'string', array('length' => 255));
        $t->addColumn('context', 'string', array('length' => 4000));
        $t->addColumn('references', 'string', array('length' => 32));
        $t->addColumn('insertion_date', 'integer');
        $t->setPrimaryKey(array('migration'));

        foreach ($schema->toSql($dbPlatform) as $sql) {
            $this->dbHandler->exec($sql);
        }
    }

    protected function arrayToContext(array $data)
    {
        return json_decode($data['context'], true);
    }
}

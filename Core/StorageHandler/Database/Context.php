<?php

namespace Kaliop\eZMigrationBundle\Core\StorageHandler\Database;

use Kaliop\eZMigrationBundle\API\ContextStorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;

class Context extends TableStorage implements ContextStorageHandlerInterface
{
    public function loadMigrationContext($migrationName)
    {

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
}
<?php

namespace Kaliop\eZMigrationBundle\Core\StorageHandler\Database;

use eZ\Publish\Core\Persistence\Database\DatabaseHandler;

abstract class TableStorage
{
    /**
     * Name of the database table where data is stored.
     * @var string $tableName
     *
     * @todo add setter/getter, as we need to clear versionTableExists when switching this
     */
    protected $tableName;

    /**
     * @var DatabaseHandler $dbHandler
     */
    protected $dbHandler;

    /**
     * Flag to indicate that the migration table has been created
     *
     * @var boolean $tableExists
     */
    protected $tableExists = false;

    /**
     * @param DatabaseHandler $dbHandler
     * @param string $tableName
     */
    public function __construct(DatabaseHandler $dbHandler, $tableName)
    {
        $this->dbHandler = $dbHandler;
        $this->tableName = $tableName;
    }

    /**
     * @return mixed
     */
    protected function getConnection()
    {
        return $this->dbHandler->getConnection();
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

    /**
     * Check if the version db table exists and create it if not.
     *
     * @return bool true if table has been created, false if it was already there
     *
     * @todo add a 'force' flag to force table re-creation
     * @todo manage changes to table definition
     */
    protected function createTableIfNeeded()
    {
        if ($this->tableExists) {
            return false;
        }

        if ($this->tableExist($this->tableName)) {
            $this->tableExists = true;
            return false;
        }

        $this->createTable();

        $this->tableExists = true;
        return true;
    }

    abstract function createTable();

    /**
     * Removes all data from storage
     */
    protected function drop()
    {
        if ($this->tableExist($this->tableName)) {
            $this->dbHandler->exec('DROP TABLE ' . $this->tableName);
        }
    }
}
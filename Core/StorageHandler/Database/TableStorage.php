<?php

namespace Kaliop\eZMigrationBundle\Core\StorageHandler\Database;

use Doctrine\DBAL\Schema\Table;
use eZ\Publish\Core\Persistence\Database\QueryException;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;
use Kaliop\eZMigrationBundle\API\ConfigResolverInterface;

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

    /** @var array $tableCreationOptions */
    protected $tableCreationOptions;

    /**
     * @param DatabaseHandler $dbHandler
     * @param string $tableNameParameter name of table when $configResolver is null, name of parameter otherwise
     * @param ConfigResolverInterface $configResolver
     * @param array $tableCreationOptions
     * @throws \Exception
     */
    public function __construct(DatabaseHandler $dbHandler, $tableNameParameter, ConfigResolverInterface $configResolver = null, $tableCreationOptions = array())
    {
        $this->dbHandler = $dbHandler;
        $this->tableName = $configResolver ? $configResolver->getParameter($tableNameParameter) : $tableNameParameter;
        $this->tableCreationOptions = $tableCreationOptions;
    }

    abstract function createTable();

    /**
     * To be called from createTable to add to table definitions the common options (storage engine, charset, etc...)
     * @param Table $table
     */
    protected function injectTableCreationOptions(Table $table)
    {
        foreach($this->tableCreationOptions as $key => $value) {
            $table->addOption($key, $value);
        }
    }

    /**
     * @return \Doctrine\DBAL\Connection|mixed
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

    /**
     * Removes all data from storage as well as removing the tables itself
     * @throws QueryException
     */
    protected function drop()
    {
        if ($this->tableExist($this->tableName)) {
            $this->dbHandler->exec('DROP TABLE ' . $this->tableName);
        }
    }

    /**
     * Removes all data from storage
     * @throws QueryException
     */
    public function truncate()
    {
        if ($this->tableExist($this->tableName)) {
            $this->dbHandler->exec('TRUNCATE ' . $this->tableName);
        }
    }
}

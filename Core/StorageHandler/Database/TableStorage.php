<?php

namespace Kaliop\eZMigrationBundle\Core\StorageHandler\Database;

use Doctrine\DBAL\Connection;
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

    /** @var \Doctrine\DBAL\Connection */
    protected $connection;

    /**
     * Flag to indicate that the migration table has been created
     *
     * @var boolean $tableExists
     */
    protected $tableExists = false;

    /**
     * @param \Doctrine\DBAL\Connection $connection
     * @param string $tableNameParameter name of table when $configResolver is null, name of parameter otherwise
     * @param ConfigResolverInterface $configResolver
     * @throws \Exception
     */
    public function __construct(Connection $connection, $tableNameParameter, ConfigResolverInterface $configResolver = null)
    {
        $this->connection = $connection;
        $this->tableName = $configResolver ? $configResolver->getParameter($tableNameParameter) : $tableNameParameter;
    }

    abstract function createTable();

    /**
     * @return \Doctrine\DBAL\Connection
     */
    protected function getConnection()
    {
        return $this->connection;
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
        $sm = $this->getConnection()->getSchemaManager();
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
     */
    protected function drop()
    {
        if ($this->tableExist($this->tableName)) {
            $this->connection->exec('DROP TABLE ' . $this->tableName);
        }
    }

    /**
     * Removes all data from storage
     */
    public function truncate()
    {
        if ($this->tableExist($this->tableName)) {
            $this->connection->exec('TRUNCATE ' . $this->tableName);
        }
    }
}

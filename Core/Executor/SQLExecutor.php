<?php


namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\ExecutorInterface;
use eZ\Publish\Core\Persistence\Database\DatabaseHandler;

class SQLExecutor implements ExecutorInterface
{
    /**
     * @var DatabaseHandler $connection
     */
    protected $connection;

    /**
     * @param DatabaseHandler $connection
     */
    public function __construct(DatabaseHandler $connection)
    {
        $this->connection = $connection;
    }

    public function supportedTypes()
    {
        return array('php_method', 'php_class', 'symfony_service');
    }

    /**
     * @param string $type
     * @param array $dsl
     * @return void
     */
    public function execute($type, array $dsl = array())
    {
    }
}
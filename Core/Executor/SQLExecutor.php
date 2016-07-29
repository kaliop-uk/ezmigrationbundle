<?php


namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\ExecutorInterface;

class SQLExecutor implements ExecutorInterface
{
    public function supportedTypes()
    {
        return array('php_method', 'php_class', 'symfony_service');
    }

    /**
     * @param array $dsl
     * @return void
     */
    public function execute($type, array $dsl = array())
    {
    }
}
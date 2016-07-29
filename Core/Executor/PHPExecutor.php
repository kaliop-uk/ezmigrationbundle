<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\ExecutorInterface;

class PHPExecutor implements ExecutorInterface
{
    public function supportedTypes()
    {
        return array('sql');
    }

    /**
     * @param array $dsl
     * @return void
     */
    public function execute($type, array $dsl = array())
    {
    }
}
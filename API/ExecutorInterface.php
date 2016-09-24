<?php

namespace Kaliop\eZMigrationBundle\API;

use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

/**
 * Interface that all migration definition handlers need to implement.
 *
 * @todo we should add here the methods that allow validation of a $dsl...
 */
interface ExecutorInterface
{

    /**
     * Returns the list of supported types of actions (the 'type' key in the yml files)
     *
     * @return string[]
     */
    public function supportedTypes();

    /**
     * Execute a single action in a migration version
     *
     * @param MigrationStep $step
     * @return mixed the results of the execution step are wrapped in an event which can be listened to
     * @throws \Exception
     */
    public function execute(MigrationStep $step);
}

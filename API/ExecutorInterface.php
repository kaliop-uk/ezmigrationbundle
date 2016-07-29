<?php

namespace Kaliop\eZMigrationBundle\API;

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
     * @param $type
     * @param array $dsl the definition of the action
     * @return void
     */
    public function execute($type, array $dsl = array());
}

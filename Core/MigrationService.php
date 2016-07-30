<?php

namespace Kaliop\eZMigrationBundle\Core;

use Kaliop\eZMigrationBundle\API\StorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\LoaderInterface;
use Kaliop\eZMigrationBundle\API\DefinitionHandlerInterface;
use Kaliop\eZMigrationBundle\API\ExecutorInterface;

class MigrationService
{
    /**
     * @var LoaderInterface $loader
     */
    protected $loader;
    /**
     * @var StorageHandlerInterface $storageHandler
     */
    protected $storageHandler;

    protected $definitionHandlers = array();

    protected $executors = array();

    public function __construct(LoaderInterface $loader, StorageHandlerInterface $storageHandler)
    {
        $this->loader = $loader;
        $this->storageHandler = $storageHandler;
    }

    public function addDefinitionHandler(DefinitionHandlerInterface $definitionHandler)
    {
        $this->definitionHandlers[] = $definitionHandler;
    }

    public function addExecutor(ExecutorInterface $executor)
    {
        foreach($executor->supportedTypes() as $type) {
            $this->executors[$type] = $executor;
        }
    }
}
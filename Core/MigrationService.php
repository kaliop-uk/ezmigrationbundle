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

    /** @var DefinitionHandlerInterface[] $definitionHandlers */
    protected $definitionHandlers = array();

    /** @var ExecutorInterface[] $executors */
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

    /**
     * @param string[] $paths
     * @return string[] key: migration name, value: migration definition as binary string
     */
    public function getDefinitions($paths = array())
    {
        // we try to be flexible in file types we support, and the same time avoid loading all files in a directory
        $handledDefinitions = array();
        foreach($this->loader->listAvailableDefinitions($paths) as $migration => $definitionPath) {
            foreach($this->definitionHandlers as $definitionHandler) {
                if ($definitionHandler->supports($migration)) {
                    $handledDefinitions[] = $definitionPath;
                }
            }
        }

        return $this->loader->loadDefinitions($handledDefinitions);
    }

    public function getMigrations()
    {
        return $this->storageHandler->loadMigrations();
    }
}
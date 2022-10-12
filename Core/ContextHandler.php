<?php

namespace Kaliop\eZMigrationBundle\Core;

use Kaliop\eZMigrationBundle\API\ContextStorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\ContextProviderInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;

/**
 * Takes care of storing / restoring all necessary migration execution context information, acting as a "multiplexer":
 * services which hold context information have to implement the ContextProviderInterface interface
 */
class ContextHandler
{
    protected $storageHandler;
    /** @var ContextProviderInterface[] $providers  */
    protected $providers = array();

    public function __construct(ContextStorageHandlerInterface $storageHandler)
    {
        $this->storageHandler = $storageHandler;
    }

    /**
     * @param ContextProviderInterface $contextProvider
     * @param string $label
     */
    public function addProvider(ContextProviderInterface $contextProvider, $label)
    {
        $this->providers[$label] = $contextProvider;
    }

    /**
     * @param string $migrationName
     */
    public function storeCurrentContext($migrationName)
    {
        $context = array();
        foreach ($this->providers as $label => $provider) {
            $context[$label] = $provider->getCurrentContext($migrationName);
        }

        $this->storageHandler->storeMigrationContext($migrationName, $context);
    }

    /**
     * @param string $migrationName
     * @throws \Exception
     */
    public function restoreCurrentContext($migrationName)
    {
        $context = $this->storageHandler->loadMigrationContext($migrationName);
        if (!is_array($context)) {
            throw new MigrationBundleException("No execution context found associated with migration '$migrationName'");
        }
        foreach ($this->providers as $label => $provider) {
            if (isset($context[$label])) {
                $provider->restoreContext($migrationName, $context[$label]);
            }
        }
    }

    public function deleteContext($migrationName)
    {
        $this->storageHandler->deleteMigrationContext($migrationName);
    }
}

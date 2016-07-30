<?php

namespace Kaliop\eZMigrationBundle\Core;

use Kaliop\eZMigrationBundle\API\StorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\LoaderInterface;
use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
use Kaliop\eZMigrationBundle\API\ExecutorInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;

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

    /** @var DefinitionParserInterface[] $DefinitionParsers */
    protected $DefinitionParsers = array();

    /** @var ExecutorInterface[] $executors */
    protected $executors = array();

    public function __construct(LoaderInterface $loader, StorageHandlerInterface $storageHandler)
    {
        $this->loader = $loader;
        $this->storageHandler = $storageHandler;
    }

    public function addDefinitionParser(DefinitionParserInterface $DefinitionParser)
    {
        $this->DefinitionParsers[] = $DefinitionParser;
    }

    public function addExecutor(ExecutorInterface $executor)
    {
        foreach($executor->supportedTypes() as $type) {
            $this->executors[$type] = $executor;
        }
    }

    /**
     * NB: returns UNPARSED definitions
     *
     * @param string[] $paths
     * @return \Kaliop\eZMigrationBundle\API\Value\MigrationDefinition[] key: migration name, value: migration definition as binary string
     */
    public function getMigrationsDefinitions($paths = array())
    {
        // we try to be flexible in file types we support, and the same time avoid loading all files in a directory
        $handledDefinitions = array();
        foreach($this->loader->listAvailableDefinitions($paths) as $migrationName => $definitionPath) {
            foreach($this->DefinitionParsers as $definitionParser) {
                if ($definitionParser->supports($migrationName)) {
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

    public function parseMigrationDefinition(MigrationDefinition $migrationDefinition)
    {
        foreach($this->DefinitionParsers as $definitionParser) {
            if ($definitionParser->supports($migrationDefinition->name)) {
                return $definitionParser->parseMigrationDefinition($migrationDefinition);
            }
        }

        throw new \Exception("No parser available to parse migration definition '$migrationDefinition'");
    }
}
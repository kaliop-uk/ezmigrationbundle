<?php

namespace Kaliop\eZMigrationBundle\Core;

use Kaliop\eZMigrationBundle\API\StorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\LoaderInterface;
use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
use Kaliop\eZMigrationBundle\API\ExecutorInterface;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use eZ\Publish\API\Repository\Repository;

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

    protected $repository;

    public function __construct(LoaderInterface $loader, StorageHandlerInterface $storageHandler, Repository $repository)
    {
        $this->loader = $loader;
        $this->storageHandler = $storageHandler;
        $this->repository = $repository;
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

    /**
     * Return the list of all the migrations which where executed or attempted so far
     *
     * @return \Kaliop\eZMigrationBundle\API\Collection\MigrationCollection
     */
    public function getMigrations()
    {
        return $this->storageHandler->loadMigrations();
    }

    /**
     * Parses a migration definition, return a parsed definition.
     * If there is a parsing error, the definition status will be updated accordingly
     *
     * @param MigrationDefinition $migrationDefinition
     * @return MigrationDefinition
     * @throws \Exception if the migrationDefinition has no suitable parser for its source format
     */
    public function parseMigrationDefinition(MigrationDefinition $migrationDefinition)
    {
        foreach($this->DefinitionParsers as $definitionParser) {
            if ($definitionParser->supports($migrationDefinition->name)) {
                // parse the source file
                $migrationDefinition = $definitionParser->parseMigrationDefinition($migrationDefinition);

                // and make sure we know how to handle all steps
                foreach($migrationDefinition->steps as $step) {
                    if (!isset($this->executors[$step->type])) {
                        return new MigrationDefinition(
                            $migrationDefinition->name,
                            $migrationDefinition->path,
                            $migrationDefinition->rawDefinition,
                            MigrationDefinition::STATUS_INVALID,
                            array(),
                            "Can not handle migration step of type '{$step->type}'"
                        );
                    }
                }

                return $migrationDefinition;
            }
        }

        throw new \Exception("No parser available to parse migration definition '$migrationDefinition'");
    }

    /**
     * @param MigrationDefinition $migrationDefinition
     * @throws \Exception
     *
     * @todo add support for skipped migrations, partially executed migrations
     */
    public function executeMigration(MigrationDefinition $migrationDefinition)
    {
        if ($migrationDefinition->status == MigrationDefinition::STATUS_TO_PARSE) {
            $migrationDefinition = $this->parseMigrationDefinition($migrationDefinition);
        }

        if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
            throw new \Exception("Can not execute migration '{$migrationDefinition->name}': {$migrationDefinition->parsingError}");
        }

        // set migration as begun - has to be in own db transaction
        $migration = $this->storageHandler->startMigration($migrationDefinition);

        $this->repository->beginTransaction();
        try {

            foreach($migrationDefinition->steps as $step) {
                // we validated the fact that we have a good executor at parsing time
                $executor = $this->executors[$step->type];
                $executor->execute($step);
            }

            $status = Migration::STATUS_DONE;

            // set migration as done
            $this->storageHandler->endMigration(new Migration(
                $migration->name,
                $migration->md5,
                $migration->path,
                $migration->executionDate,
                $status
            ));

            $this->repository->commit();

        } catch(\Exception $e) {
            $this->repository->rollBack();

            /// set migration as failed
            $this->storageHandler->endMigration(new Migration(
                $migration->name,
                $migration->md5,
                $migration->path,
                $migration->executionDate,
                Migration::STATUS_FAILED,
                $e->getMessage()
            ));

            throw $e;
        }
    }
}
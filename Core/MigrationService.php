<?php

namespace Kaliop\eZMigrationBundle\Core;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use eZ\Publish\API\Repository\Repository;
use Kaliop\eZMigrationBundle\API\Collection\MigrationDefinitionCollection;
use Kaliop\eZMigrationBundle\API\LanguageAwareInterface;
use Kaliop\eZMigrationBundle\API\StorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\LoaderInterface;
use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
use Kaliop\eZMigrationBundle\API\ExecutorInterface;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Exception\MigrationStepExecutionException;
use Kaliop\eZMigrationBundle\API\Event\BeforeStepExecutionEvent;
use Kaliop\eZMigrationBundle\API\Event\StepExecutedEvent;

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

    protected $dispatcher;

    public function __construct(LoaderInterface $loader, StorageHandlerInterface $storageHandler, Repository $repository, EventDispatcherInterface $eventDispatcher)
    {
        $this->loader = $loader;
        $this->storageHandler = $storageHandler;
        $this->repository = $repository;
        $this->dispatcher = $eventDispatcher;
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
     * @return MigrationDefinitionCollection key: migration name, value: migration definition as binary string
     */
    public function getMigrationsDefinitions(array $paths = array())
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

        // we can not call loadDefinitions with an empty array using the Filesystem loader, or it will start looking in bundles...
        if (empty($handledDefinitions) && !empty($paths)) {
            return new MigrationDefinitionCollection();
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
     * @param string $migrationName
     * @return Migration|null
     */
    public function getMigration($migrationName)
    {
        return $this->storageHandler->loadMigration($migrationName);
    }

    /**
     * @param MigrationDefinition $migrationDefinition
     * @return Migration
     */
    public function addMigration(MigrationDefinition $migrationDefinition)
    {
        return $this->storageHandler->addMigration($migrationDefinition);
    }

    /**
     * @param Migration $migration
     */
    public function deleteMigration(Migration $migration)
    {
        return $this->storageHandler->deleteMigration($migration);
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
     * @param bool $useTransaction when set to false, no repo transaction will be used to wrap the migration
     * @param string $defaultLanguageCode
     * @throws \Exception
     *
     * @todo add support for skipped migrations, partially executed migrations
     */
    public function executeMigration(MigrationDefinition $migrationDefinition, $useTransaction = true, $defaultLanguageCode = null)
    {
        if ($migrationDefinition->status == MigrationDefinition::STATUS_TO_PARSE) {
            $migrationDefinition = $this->parseMigrationDefinition($migrationDefinition);
        }

        if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
            throw new \Exception("Can not execute migration '{$migrationDefinition->name}': {$migrationDefinition->parsingError}");
        }

        // Inject default language code in executors that support it.
        if ($defaultLanguageCode) {
            foreach ($this->executors as $executor) {
                if ($executor instanceof LanguageAwareInterface) {
                    $executor->setDefaultLanguageCode($defaultLanguageCode);
                }
            }
        }

        // set migration as begun - has to be in own db transaction
        $migration = $this->storageHandler->startMigration($migrationDefinition);

        if ($useTransaction) {
            $this->repository->beginTransaction();
        }
        try {

            $i = 1;

            foreach($migrationDefinition->steps as $step) {
                // we validated the fact that we have a good executor at parsing time
                $executor = $this->executors[$step->type];

                $this->dispatcher->dispatch('ez_migration.before_execution', new BeforeStepExecutionEvent($step, $executor));

                $result = $executor->execute($step);

                $this->dispatcher->dispatch('ez_migration.step_executed', new StepExecutedEvent($step, $result));

                $i++;
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

            if ($useTransaction) {
                try {
                    $this->repository->commit();
                } catch(\RuntimeException $e) {
                    // at present time, the ez5 repo does not support nested commits. So if some migration step has committed
                    // already, we get an exception a this point. Extremely poor design, but what can we do ?
                    /// @todo log warning
                }
            }

        } catch(\Exception $e) {

            if ($useTransaction) {
                try {
                    $this->repository->rollBack();
                } catch(\RuntimeException $e2) {
                    // at present time, the ez5 repo does not support nested commits. So if some migration step has committed
                    // already, we get an exception a this point. Extremely poor design, but what can we do ?
                    /// @todo log error
                }
            }

            /// set migration as failed
            $this->storageHandler->endMigration(new Migration(
                $migration->name,
                $migration->md5,
                $migration->path,
                $migration->executionDate,
                Migration::STATUS_FAILED,
                $e->getMessage()
            ));

            throw new MigrationStepExecutionException($this->getFullExceptionMessage($e) . ' in file ' . $e->getFile() . ' line ' . $e->getLine(), $i, $e);
        }
    }

    /**
     * Turns eZPublish cryptic exceptions into something more palatable for random devs
     * @todo should this be moved to a lower layer ?
     *
     * @param \Exception $e
     * @return string
     */
    protected function getFullExceptionMessage(\Exception $e)
    {
        $message = $e->getMessage();
        if (is_a($e, '\eZ\Publish\API\Repository\Exceptions\ContentTypeFieldDefinitionValidationException') ||
            //is_a($e, '\eZ\Publish\API\Repository\Exceptions\ContentTypeFieldValidationException') ||
            is_a($e, '\eZ\Publish\API\Repository\Exceptions\LimitationValidationException')
        ) {
            if (is_a($e, '\eZ\Publish\API\Repository\Exceptions\LimitationValidationException')) {
                $errorsArray = $e->getLimitationErrors();
                if ($errorsArray == null) {
                    return $message;
                }
            } else {
                $errorsArray = $e->getFieldErrors();
            }

            foreach ($errorsArray as $errors) {
                // sometimes error arrays are 2-level deep, sometimes 1...
                if (!is_array($errors)) {
                    $errors = array($errors);
                }
                foreach ($errors as $error) {
                    /// @todo find out what is the proper eZ way of getting a translated message for these errors
                    $translatableMessage = $error->getTranslatableMessage();
                    if (is_a($e, 'eZ\Publish\API\Repository\Values\Translation\Plural')) {
                        $msgText = $translatableMessage->plural;
                    } else {
                        $msgText = $translatableMessage->message;
                    }

                    $message .= "\n" . $msgText . " - " . var_export($translatableMessage->values, true);
                }
            }
        }
        return $message;
    }
}

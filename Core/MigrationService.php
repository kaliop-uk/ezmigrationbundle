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
use Kaliop\eZMigrationBundle\API\Exception\MigrationAbortedException;
use Kaliop\eZMigrationBundle\API\Event\BeforeStepExecutionEvent;
use Kaliop\eZMigrationBundle\API\Event\StepExecutedEvent;
use Kaliop\eZMigrationBundle\API\Event\MigrationAbortedEvent;

class MigrationService
{
    use RepositoryUserSetterTrait;

    /**
     * Constant defining the default Admin user ID.
     * @todo inject via config parameter
     */
    const ADMIN_USER_ID = 14;

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

    protected $eventPrefix = 'ez_migration.';

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
        foreach ($executor->supportedTypes() as $type) {
            $this->executors[$type] = $executor;
        }
    }

    /**
     * @param string $type
     * @return ExecutorInterface
     * @throws \InvalidArgumentException If executor doesn't exist
     */
    public function getExecutor($type)
    {
        if (!isset($this->executors[$type])) {
            throw new \InvalidArgumentException("Executor with type '$type' doesn't exist");
        }

        return $this->executors[$type];
    }

    /**
     * @return string[]
     */
    public function listExecutors()
    {
        return array_keys($this->executors);
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
        foreach ($this->loader->listAvailableDefinitions($paths) as $migrationName => $definitionPath) {
            foreach ($this->DefinitionParsers as $definitionParser) {
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
     * Returns the list of all the migrations which where executed or attempted so far
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
     * @param MigrationDefinition $migrationDefinition
     * @return Migration
     */
    public function skipMigration(MigrationDefinition $migrationDefinition)
    {
        return $this->storageHandler->skipMigration($migrationDefinition);
    }

    /**
     * Not be called by external users for normal use cases, you should use executeMigration() instead
     *
     * @param Migration $migration
     */
    public function endMigration(Migration $migration)
    {
        return $this->storageHandler->endMigration($migration);
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
        foreach ($this->DefinitionParsers as $definitionParser) {
            if ($definitionParser->supports($migrationDefinition->name)) {
                // parse the source file
                $migrationDefinition = $definitionParser->parseMigrationDefinition($migrationDefinition);

                // and make sure we know how to handle all steps
                foreach ($migrationDefinition->steps as $step) {
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

        throw new \Exception("No parser available to parse migration definition '{$migrationDefinition->name}'");
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

        $previousUserId = null;

        try {

            $i = 1;
            $finalStatus = Migration::STATUS_DONE;
            $finalMessage = null;

            try {

                foreach ($migrationDefinition->steps as $step) {
                    // we validated the fact that we have a good executor at parsing time
                    $executor = $this->executors[$step->type];
                    if ($executor instanceof LanguageAwareInterface) {
                        $executor->setLanguageCode(null);
                    }

                    $beforeStepExecutionEvent = new BeforeStepExecutionEvent($step, $executor);
                    $this->dispatcher->dispatch($this->eventPrefix . 'before_execution', $beforeStepExecutionEvent);
                    // allow some sneaky trickery here: event listeners can manipulate 'live' the step definition and the executor
                    $executor = $beforeStepExecutionEvent->getExecutor();
                    $step = $beforeStepExecutionEvent->getStep();

                    $result = $executor->execute($step);

                    $this->dispatcher->dispatch($this->eventPrefix . 'step_executed', new StepExecutedEvent($step, $result));

                    $i++;
                }

            } catch (MigrationAbortedException $e) {
                // allow a migration step (or events) to abort the migration via a specific exception

                $this->dispatcher->dispatch($this->eventPrefix . 'migration_aborted', new MigrationAbortedEvent($step, $e));

                $finalStatus = $e->getCode();
                $finalMessage = "Abort in execution of step $i: " . $e->getMessage();
            }

            // set migration as done
            $this->storageHandler->endMigration(new Migration(
                $migration->name,
                $migration->md5,
                $migration->path,
                $migration->executionDate,
                $finalStatus,
                $finalMessage
            ));

            if ($useTransaction) {
                // there might be workflows or other actions happening at commit time that fail if we are not admin
                $previousUserId = $this->loginUser(self::ADMIN_USER_ID);
                $this->repository->commit();
                $this->loginUser($previousUserId);
            }

        } catch (\Exception $e) {

            $errorMessage = $this->getFullExceptionMessage($e) . ' in file ' . $e->getFile() . ' line ' . $e->getLine();
            $finalStatus = Migration::STATUS_FAILED;

            if ($useTransaction) {
                try {
                    // cater to the case where the $this->repository->commit() call above throws an exception
                    if ($previousUserId) {
                        $this->loginUser($previousUserId);
                    }

                    // there is no need to become admin here, at least in theory
                    $this->repository->rollBack();

                } catch (\Exception $e2) {
                    // This check is not rock-solid, but at the moment is all we can do to tell apart 2 cases of
                    // exceptions originating above: the case where the commit was successful but a commit-queue event
                    // failed, from the case where something failed beforehand
                    if ($previousUserId && $e2->getMessage() == 'There is no active transaction.') {
                        // since the migration succeeded and it was committed, no use to mark it as failed...
                        $finalStatus = Migration::STATUS_DONE;
                        $errorMessage = 'Error post migration execution: ' . $this->getFullExceptionMessage($e2) .
                            ' in file ' . $e2->getFile() . ' line ' . $e2->getLine();
                    } else {
                        $errorMessage .= '. In addition, an exception was thrown while rolling back: ' .
                            $this->getFullExceptionMessage($e2) . ' in file ' . $e2->getFile() . ' line ' . $e2->getLine();
                    }
                }
            }

            // set migration as failed
            // NB: we use the 'force' flag here because we might be catching an exception happened during the call to
            // $this->repository->commit() above, in which case the Migration might already be in the DB with a status 'done'
            $this->storageHandler->endMigration(
                new Migration(
                    $migration->name,
                    $migration->md5,
                    $migration->path,
                    $migration->executionDate,
                    $finalStatus,
                    $errorMessage
                ),
                true
            );

            throw $e;
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
            is_a($e, '\eZ\Publish\API\Repository\Exceptions\LimitationValidationException') ||
            is_a($e, '\eZ\Publish\Core\Base\Exceptions\ContentFieldValidationException')
        ) {
            if (is_a($e, '\eZ\Publish\API\Repository\Exceptions\LimitationValidationException')) {
                $errorsArray = $e->getLimitationErrors();
                if ($errorsArray == null) {
                    return $message;
                }
            } else if (is_a($e, '\eZ\Publish\Core\Base\Exceptions\ContentFieldValidationException')) {
                $errorsArray = array();
                foreach ($e->getFieldErrors() as $limitationError) {
                    // we get the 1st language
                    $errorsArray[] = reset($limitationError);
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
                    if (is_a($translatableMessage, '\eZ\Publish\API\Repository\Values\Translation\Plural')) {
                        $msgText = $translatableMessage->plural;
                    } else {
                        $msgText = $translatableMessage->message;
                    }

                    $message .= "\n" . $msgText . " - " . var_export($translatableMessage->values, true);
                }
            }
        }

        while (($e = $e->getPrevious()) != null) {
            $message .= "\n" . $e->getMessage();
        }

        return $message;
    }
}

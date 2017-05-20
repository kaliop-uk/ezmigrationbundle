<?php

namespace Kaliop\eZMigrationBundle\Core;

use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use eZ\Publish\API\Repository\Repository;
use Kaliop\eZMigrationBundle\API\Collection\MigrationDefinitionCollection;
use Kaliop\eZMigrationBundle\API\StorageHandlerInterface;
use Kaliop\eZMigrationBundle\API\LoaderInterface;
use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
use Kaliop\eZMigrationBundle\API\ExecutorInterface;
use Kaliop\eZMigrationBundle\API\ContextProviderInterface;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Exception\MigrationStepExecutionException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationAbortedException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationSuspendedException;
use Kaliop\eZMigrationBundle\API\Event\BeforeStepExecutionEvent;
use Kaliop\eZMigrationBundle\API\Event\StepExecutedEvent;
use Kaliop\eZMigrationBundle\API\Event\MigrationAbortedEvent;
use Kaliop\eZMigrationBundle\API\Event\MigrationSuspendedEvent;

class MigrationService implements ContextProviderInterface
{
    use RepositoryUserSetterTrait;

    /**
     * The default Admin user Id, used when no Admin user is specified
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

    /**
     * @var ContextHandler $contextHandler
     */
    protected $contextHandler;

    protected $eventPrefix = 'ez_migration.';

    protected $migrationContext = array();

    public function __construct(LoaderInterface $loader, StorageHandlerInterface $storageHandler, Repository $repository,
        EventDispatcherInterface $eventDispatcher, $contextHandler)
    {
        $this->loader = $loader;
        $this->storageHandler = $storageHandler;
        $this->repository = $repository;
        $this->dispatcher = $eventDispatcher;
        $this->contextHandler = $contextHandler;
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
     * @param int $limit 0 or below will be treated as 'no limit'
     * @param int $offset
     * @return \Kaliop\eZMigrationBundle\API\Collection\MigrationCollection
     */
    public function getMigrations($limit = null, $offset = null)
    {
        return $this->storageHandler->loadMigrations($limit, $offset);
    }

    /**
     * Returns the list of all the migrations in a given status which where executed or attempted so far
     *
     * @param int $status
     * @param int $limit 0 or below will be treated as 'no limit'
     * @param int $offset
     * @return \Kaliop\eZMigrationBundle\API\Collection\MigrationCollection
     */
    public function getMigrationsByStatus($status, $limit = null, $offset = null)
    {
        return $this->storageHandler->loadMigrationsByStatus($status, $limit, $offset);
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
     * @param string|int|false|null $adminLogin when false, current user is used; when null, hardcoded admin account
     * @throws \Exception
     *
     * @todo treating a null and false $adminLogin values differently is prone to hard-to-track errors.
     *       Shall we use instead -1 to indicate the desire to not-login-as-admin-user-at-all ?
     */
    public function executeMigration(MigrationDefinition $migrationDefinition, $useTransaction = true,
                                     $defaultLanguageCode = null, $adminLogin = null)
    {
        if ($migrationDefinition->status == MigrationDefinition::STATUS_TO_PARSE) {
            $migrationDefinition = $this->parseMigrationDefinition($migrationDefinition);
        }

        if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
            throw new \Exception("Can not execute migration '{$migrationDefinition->name}': {$migrationDefinition->parsingError}");
        }

        /// @todo add support for setting in $migrationContext a userContentType ?
        $migrationContext = $this->migrationContextFromParameters($defaultLanguageCode, $adminLogin);

        // set migration as begun - has to be in own db transaction
        $migration = $this->storageHandler->startMigration($migrationDefinition);

        $this->executeMigrationInner($migration, $migrationDefinition, $migrationContext, 0, $useTransaction, $adminLogin);
    }

    /**
     * @param Migration $migration
     * @param MigrationDefinition $migrationDefinition
     * @param array $migrationContext
     * @param int $stepOffset
     * @param bool $useTransaction when set to false, no repo transaction will be used to wrap the migration
     * @param string|int|false|null $adminLogin used only for committing db transaction if needed. If false or null, hardcoded admin is used
     * @throws \Exception
     */
    protected function executeMigrationInner(Migration $migration, MigrationDefinition $migrationDefinition,
        $migrationContext, $stepOffset = 0, $useTransaction = true, $adminLogin = null)
    {
        if ($useTransaction) {
            $this->repository->beginTransaction();
        }

        $previousUserId = null;
        $steps = array_slice($migrationDefinition->steps->getArrayCopy(), $stepOffset);

        try {

            $i = $stepOffset+1;
            $finalStatus = Migration::STATUS_DONE;
            $finalMessage = null;

            try {

                foreach ($steps as $step) {

                    $step = $this->injectContextIntoStep($step, $migrationContext);

                    // we validated the fact that we have a good executor at parsing time
                    $executor = $this->executors[$step->type];

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
            } catch (MigrationSuspendedException $e) {
                // allow a migration step (or events) to suspend the migration via a specific exception

                $this->dispatcher->dispatch($this->eventPrefix . 'migration_suspended', new MigrationSuspendedEvent($step, $e));

                // prepare data for the context handler
                $this->migrationContext[$migration->name] = array('step' => $i, 'context' => $migrationContext);
                // let the context handler store our data, along context data from any other (tagged) service which has some
                $this->contextHandler->storeCurrentContext($migration->name);

                $finalStatus = Migration::STATUS_SUSPENDED;
                $finalMessage = "Suspended in execution of step $i: " . $e->getMessage();
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
                $previousUserId = $this->loginUser($this->getAdminUserIdentifier($adminLogin));

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

            throw new MigrationStepExecutionException($errorMessage, $i, $e);
        }
    }

    /**
     * @param Migration $migration
     * @param bool $useTransaction
     * @throws \Exception
     *
     * @todo add support for adminLogin ?
     */
    public function resumeMigration(Migration $migration, $useTransaction = true)
    {
        if ($migration->status != Migration::STATUS_SUSPENDED) {
            throw new \Exception("Can not resume migration '{$migration->name}': it is not in suspended status");
        }

        $migrationDefinitions = $this->getMigrationsDefinitions(array($migration->path));
        if (!count($migrationDefinitions)) {
            throw new \Exception("Can not resume migration '{$migration->name}': its definition is missing");
        }

        $defs = $migrationDefinitions->getArrayCopy();
        $migrationDefinition = reset($defs);

        $migrationDefinition = $this->parseMigrationDefinition($migrationDefinition);
        if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
            throw new \Exception("Can not resume migration '{$migration->name}': {$migrationDefinition->parsingError}");
        }

        // restore context
        $this->contextHandler->restoreCurrentContext($migration->name);
        $restoredContext = $this->migrationContext[$migration->name];

        /// @todo check that restored context is valid

        // update migration status
        $migration = $this->storageHandler->resumeMigration($migration);

        // clean up restored context - ideally it should be in the same db transaction as the line above
        $this->contextHandler->deleteContext($migration->name);

        // and go
        // note: we store the current step counting starting at 1, but use offset staring at 0, hence the -1 here
        $this->executeMigrationInner($migration, $migrationDefinition, $restoredContext['context'],
            $restoredContext['step'] - 1, $useTransaction);
    }

    /**
     * @param string $defaultLanguageCode
     * @param string|int|false $adminLogin
     * @return array
     */
    protected function migrationContextFromParameters($defaultLanguageCode = null, $adminLogin = null)
    {
        $properties = array();

        if ($defaultLanguageCode != null) {
            $properties['defaultLanguageCode'] = $defaultLanguageCode;
        }
        // nb: other parts of the codebase treat differently a false and null values for $properties['adminUserLogin']
        if ($adminLogin !== null) {
            $properties['adminUserLogin'] = $adminLogin;
        }

        return $properties;
    }

    protected function injectContextIntoStep(MigrationStep $step, array $context)
    {
        return new MigrationStep(
            $step->type,
            $step->dsl,
            array_merge($step->context, $context)
        );
    }

    /**
     * @param string $adminLogin
     * @return int|string
     */
    protected function getAdminUserIdentifier($adminLogin)
    {
        if ($adminLogin != null) {
            return $adminLogin;
        }

        return self::ADMIN_USER_ID;
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

    /**
     * @param string $migrationName
     * @return array
     */
    public function getCurrentContext($migrationName)
    {
        return isset($this->migrationContext[$migrationName]) ? $this->migrationContext[$migrationName] : null;
    }

    /**
     * @param string $migrationName
     * @param array $context
     */
    public function restoreContext($migrationName, array $context)
    {
        $this->migrationContext[$migrationName] = $context;
    }
}

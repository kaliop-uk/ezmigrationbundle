<?php

namespace Kaliop\eZMigrationBundle\Core;

use eZ\Publish\API\Repository\Repository;
use Kaliop\eZMigrationBundle\API\ReferenceBagInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
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
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationSuspendedException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationStepSkippedException;
use Kaliop\eZMigrationBundle\API\Exception\AfterMigrationExecutionException;
use Kaliop\eZMigrationBundle\API\Event\BeforeStepExecutionEvent;
use Kaliop\eZMigrationBundle\API\Event\StepExecutedEvent;
use Kaliop\eZMigrationBundle\API\Event\MigrationAbortedEvent;
use Kaliop\eZMigrationBundle\API\Event\MigrationSuspendedEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/// @todo replace transaction-manager trait with a transaction-manager service
class MigrationService implements ContextProviderInterface
{
    use RepositoryUserSetterTrait;
    use TransactionManagerTrait;

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

    protected $dispatcher;

    /**
     * @var ContextHandler $contextHandler
     */
    protected $contextHandler;

    protected $eventPrefix = 'ez_migration.';

    protected $eventEntity = 'migration';

    protected $migrationContext = array();

    /** @var  OutputInterface $output */
    protected $output;

    /** @var ReferenceBagInterface */
    protected $referenceResolver;

    public function __construct(LoaderInterface $loader, StorageHandlerInterface $storageHandler, Repository $repository,
        EventDispatcherInterface $eventDispatcher, $contextHandler, $referenceResolver)
    {
        $this->loader = $loader;
        $this->storageHandler = $storageHandler;
        $this->repository = $repository;
        $this->dispatcher = $eventDispatcher;
        $this->contextHandler = $contextHandler;
        $this->referenceResolver = $referenceResolver;
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

    public function setLoader(LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    /**
     * @todo we could get rid of this by getting $output passed as argument to self::executeMigration. We are not doing
     *       that for BC for the moment (self::executeMigration api should be redone, but it is used in WorkfloBundle too)
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * NB: returns UNPARSED definitions
     *
     * @param string[] $paths
     * @return MigrationDefinitionCollection key: migration name, value: migration definition as binary string
     * @throws \Exception
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

    public function getMigrationsByPaths(array $paths, $limit = null, $offset = null)
    {
        return $this->storageHandler->loadMigrationsByPaths($paths, $limit, $offset);
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
     * Not to be called by external users for normal use cases, you should use executeMigration() instead
     *
     * @param Migration $migration
     */
    public function endMigration(Migration $migration)
    {
        return $this->storageHandler->endMigration($migration);
    }

    /**
     * Not to be called by external users for normal use cases, you should use executeMigration() instead.
     * NB: will act regardless of current migration status.
     *
     * @param Migration $migration
     */
    public function failMigration(Migration $migration, $errorMessage)
    {
        return $this->storageHandler->endMigration(
            new Migration(
                $migration->name,
                $migration->md5,
                $migration->path,
                $migration->executionDate,
                Migration::STATUS_FAILED,
                $errorMessage
            ),
            true
        );
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

        throw new MigrationBundleException("No parser available to parse migration definition '{$migrationDefinition->name}'");
    }

    /**
     * Note: previous API is kept for BC (subclasses reimplementing this method).
     * @param MigrationDefinition $migrationDefinition
     * @param array $migrationContext Supported array keys are: adminUserLogin, defaultLanguageCode,
     *                                forcedReferences, forceExecution, userContentType, userGroupContentType,
     *                                useTransaction.
     *                                Bool usage is deprecated. It was: when set to false, no repo transaction will be used to wrap the migration
     * @param string $defaultLanguageCode Deprecated - use $migrationContext['defaultLanguageCode']
     * @param string|int|false|null $adminLogin Deprecated - use $migrationContext['adminLogin']; when false, current user is used; when null, hardcoded admin account
     * @param bool $force Deprecated - use $migrationContext['forceExecution']; when true, execute a migration if it was already in status DONE or SKIPPED (would throw by default)
     * @param bool|null $forceSigchildEnabled Deprecated
     * @throws \Exception
     *
     * @todo treating a null and false $adminLogin values differently is prone to hard-to-track errors.
     *       Shall we use instead -1 to indicate the desire to not-login-as-admin-user-at-all ?
     */
    public function executeMigration(MigrationDefinition $migrationDefinition, $migrationContext = true,
        $defaultLanguageCode = null, $adminLogin = null, $force = false, $forceSigchildEnabled = null)
    {
        if ($migrationDefinition->status == MigrationDefinition::STATUS_TO_PARSE) {
            $migrationDefinition = $this->parseMigrationDefinition($migrationDefinition);
        }

        if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
            throw new MigrationBundleException("Can not execute " . $this->getEntityName($migrationDefinition). " '{$migrationDefinition->name}': {$migrationDefinition->parsingError}");
        }

        // BC: handling of legacy method call signature
        if (!is_array($migrationContext)) {
            $useTransaction = $migrationContext;
            $migrationContext = $this->migrationContextFromParameters($defaultLanguageCode, $adminLogin, $forceSigchildEnabled);
            $migrationContext['useTransaction'] = $useTransaction;
            $migrationContext['forceExecution'] = $force;
        } else {
            if ($defaultLanguageCode !== null || $adminLogin !== null || $force !== false || $forceSigchildEnabled !== null) {
                throw new MigrationBundleException("Invalid call to executeMigration: argument types mismatch");
            }
        }
        if ($this->output) {
            $migrationContext['output'] = $this->output;
        }
        $forceExecution = array_key_exists('forceExecution', $migrationContext) ? $migrationContext['forceExecution'] : false;

        /// @todo log a warning if there are already db transactions active (an active pdo-only transaction will result in an
        ///       exception, but a dbal transaction will result in not committing immediately transaction status update)

        // set migration as begun - has to be in own db transaction
        $migration = $this->storageHandler->startMigration($migrationDefinition, $forceExecution);

        $this->executeMigrationInner($migration, $migrationDefinition, $migrationContext);
    }

    /**
     * Note: previous API is kept for BC (subclasses reimplementing this method).
     * @param Migration $migration
     * @param MigrationDefinition $migrationDefinition
     * @param array $migrationContext
     * @param int $stepOffset
     * @param bool $useTransaction Deprecated - replaced by $migrationContext['useTransaction']. When set to false, no repo transaction will be used to wrap the migration
     * @param string|int|false|null $adminLogin Deprecated - $migrationContext['adminUserLogin']. Used only for committing db transaction if needed. If false or null, hardcoded admin is used
     * @throws \Exception
     */
    protected function executeMigrationInner(Migration $migration, MigrationDefinition $migrationDefinition,
        $migrationContext, $stepOffset = 0, $useTransaction = true, $adminLogin = null)
    {
        /// @todo can we make this validation smarter / move it somewhere else?
        if (array_key_exists('path', $migrationContext) || array_key_exists('contentTypeIdentifier', $migrationContext) ||
            array_key_exists('fieldIdentifier', $migrationContext)) {
            throw new MigrationBundleException("Invalid call to executeMigrationInner: forbidden elements in migrationContext");
        }

        // BC: handling of legacy method call signature
        $useTransaction = array_key_exists('useTransaction', $migrationContext) ? $migrationContext['useTransaction'] : $useTransaction;
        $adminLogin = array_key_exists('adminUserLogin', $migrationContext) ? $migrationContext['adminUserLogin'] : $adminLogin;

        $messageSuffix = '';
        if (isset($migrationContext['forcedReferences']) && count($migrationContext['forcedReferences'])) {
            $messageSuffix = array();
            foreach ($migrationContext['forcedReferences'] as $name => $value) {
                $this->referenceResolver->addReference($name, $value, true);
                $messageSuffix[] = "$name: $value";
            }
            $messageSuffix = 'Injected references: ' . implode(', ', $messageSuffix);
        }

        $this->migrationContext[$migration->name] = array('context' => $migrationContext);

        $steps = array_slice($migrationDefinition->steps->getArrayCopy(), $stepOffset);
        $i = $stepOffset+1;
        $finalStatus = Migration::STATUS_DONE;
        $finalMessage = '';
        $error = null;
        $isCommitting = false;
        $requires = null;

        try {

            if ($useTransaction) {
                /// @todo in case there is already a db transaction running, we should throw - or at least
                ///       give a warning
                try {
                    $this->beginTransaction();
                    $requires = 'commit';
                } catch (\Exception $e) {
                    $finalStatus = Migration::STATUS_FAILED;
                    $finalMessage = 'An exception was thrown while starting the transaction before the migration: ' . $this->getFullExceptionMessage($e, true);
                    /// @todo use a more specific exception?
                    $error = new MigrationBundleException($finalMessage, 0, $e);
                    $steps = array();
                }
            }

            if ($steps) {
                /// @todo catch errors thrown here
                $startTransactionLevel = $this->getDBTransactionNestingLevel();
            }

            try {
                foreach ($steps as $step) {
                    // save enough data in the context to be able to successfully suspend/resume
                    $this->migrationContext[$migration->name]['step'] = $i;

                    $step = $this->injectContextIntoStep($step, array_merge($migrationContext, array('step' => $i)));

                    // we validated the fact that we have a good executor at parsing time
                    $executor = $this->executors[$step->type];

                    $beforeStepExecutionEvent = new BeforeStepExecutionEvent($step, $executor);
                    $this->dispatcher->dispatch($this->eventPrefix . 'before_execution', $beforeStepExecutionEvent);
                    // allow some sneaky trickery here: event listeners can manipulate 'live' the step definition and the executor
                    $executor = $beforeStepExecutionEvent->getExecutor();
                    $step = $beforeStepExecutionEvent->getStep();

                    try {
                        $result = $executor->execute($step);

                        $this->dispatcher->dispatch($this->eventPrefix . 'step_executed', new StepExecutedEvent($step, $result));
                    } catch (MigrationStepSkippedException $e) {
                        continue;
                    }

                    $i++;
                }

            } catch (MigrationAbortedException $e) {
                // allow a migration step (or events) to abort the migration via a specific exception

                $this->dispatcher->dispatch($this->eventPrefix . $this->eventEntity . '_aborted', new MigrationAbortedEvent($step, $e));

                $finalStatus = $e->getCode();
                $finalMessage = "Abort in execution of step $i: " . $e->getMessage();

                /// @todo we should allow another type of migration aborting: one which forces rollback of changes, and
                ///       possibly exits execution with a non-zero code.
                ///       Atm it can be achieved by throwing any exception, which is easy to do in php but hard in yml...
                ///       Eg. distinguish between STATUS_PARTIALLY_DONE and STATUS_FAILED
                /*if ($e->getCode() == Migration::STATUS_FAILED) {
                    $error = $e;
                    if ($requires == 'commit') {
                        $requires = 'rollback';
                    }
                }*/

            } catch (MigrationSuspendedException $e) {
                // allow a migration step (or events) to suspend the migration via a specific exception

                $this->dispatcher->dispatch($this->eventPrefix . $this->eventEntity . '_suspended', new MigrationSuspendedEvent($step, $e));

                // let the context handler store our context, along with context data from any other (tagged) service which has some
                $this->contextHandler->storeCurrentContext($migration->name);

                $finalStatus = Migration::STATUS_SUSPENDED;
                $finalMessage = "Suspended in execution of step $i: " . $e->getMessage();

            } catch (\Exception $e) {
                /// @todo shall we emit a signal as well?

                if ($requires == 'commit') {
                    $requires = 'rollback';
                }

                $finalStatus = Migration::STATUS_FAILED;
                $finalMessage = $this->getFullExceptionMessage($e, true);
                $error = new MigrationStepExecutionException($finalMessage, $i, $e);
                $finalMessage = $error->getMessage();
            }

            /// @todo this test only works if the transaction left pending was opened using the PDO connection.
            ///       If it was opened by a stray/unterminated sql `begin`, it will not be detected, and most likely
            ///       throw an error later when we try to save the migration status (unless the migration is run
            ///       with $useTransaction, in which case the pending transaction just gets committed).
            ///       We could try to use PDO::inTransaction to check for pending transactions, but we'd
            ///       have to first check if it behaves the same way across all db/php-version combinations...
            if ($steps && ($pendingTransactionLevel = $this->getDBTransactionNestingLevel() - $startTransactionLevel) > 0) {
                // a migration step has opened a transaction and forgotten to close it! For safety, we have to roll back

                // remove extra transaction levels
                for ($i = 0; $i < $pendingTransactionLevel; $i++) {
                    /// @todo catch errors thrown here
                    $this->rollbackDBTransaction();
                }
                // if there was a transaction added by ourselves, let it be rolled back
                if ($requires == 'commit') {
                    $requires = 'rollback';
                }
                // mark the migration as failed
                $finalStatus = Migration::STATUS_FAILED;
                if ($error) {
                    /// @todo re-inject the new message into $error
                    $finalMessage .= '. In addition, the migration had left a database transaction pending';
                } else {
                    // it would be nice to tell the user which step actually has opened the transaction left dangling,
                    // but that could be complicated, taking into account the case of migrations starting a transaction
                    // in step X and closing it in step Y, as well as for the possibility of exceptions being thrown
                    // halfway through a step
                    $finalMessage = 'The migration was rolled back because it had left a database transaction pending';
                    $error = new MigrationBundleException($finalMessage);
                }
            }

            /// @todo in the same way that we check for migration steps having left a pending transaction in the code
            ///       block above, we could check if any transaction has changed the currently logged-in user, and roll
            ///       that back, too

            // in case we are wrapping the migration in a transaction, either commit or roll back
            if ($requires == 'commit') {
                try {
                    // there might be workflows or other actions happening at commit time that fail if we are not admin
                    // when committing
                    $previousUserId = null;
                    $previousUserId = $this->loginUser($this->getAdminUserIdentifier($adminLogin));

                    // Note that the repository by design does first execute a db commit, then carries out some other stuff,
                    // such as f.e. sending data to Solr. It is useful to differentiate between errors thrown during the
                    // two phases, as the end results are very different in the db, but it is hard to do so by looking
                    // into the exception that gets thrown here. So we just mark the transaction for rollback in case an
                    // error occurs, and check what happens during rollback later on.
                    $isCommitting = true;
                    $this->commit();
                    $isCommitting = false;
                } catch (\Exception $e) {
                    // When running some DDL queries, some databases (eg. mysql, oracle) do commit any pending transaction.
                    // Since php 8.0, the PDO driver does properly take that into account, and throws an exception
                    // when we try to commit here. Short of analyzing any executed migration step checking for execution
                    // of DDL, all we can do is swallow the error.
                    // NB: what we get is a chain: RuntimeException/RuntimeException/PDOException. Should we validate it fully?
                    if ($e instanceof \RuntimeException && $e->getMessage() == 'There is no active transaction') {
                        $isCommitting = false;
                        $this->resetDBTransaction();
                        // save a warning in the migration status
                        $finalMessage = 'Some migration step committed the transaction halfway';
                    } else {
                        $finalStatus = Migration::STATUS_FAILED;
                        $finalMessage = 'An exception was thrown while committing: ' . $this->getFullExceptionMessage($e, true);
                        $requires = 'rollback';
                        /// @todo use a more specific exception?
                        $error = new MigrationBundleException($finalMessage, 0, $e);
                    }
                } finally {
                    /// @todo wrap any (unlikely) exception from this call in an AfterMigrationExecutionException
                    if ($previousUserId) {
                        $this->loginUser($previousUserId);
                    }
                }
            }

            if ($requires == 'rollback') {
                try {
                    // there is no need to be admin here, at least in theory
                    $this->rollBack();
                } catch (\Exception $e) {
                    // This check is not rock-solid, but at the moment is the best we can do to tell apart 2 cases of
                    // exceptions originating above during commit: the case where the commit was successful but handling
                    // of a commit-queue signal failed, from the case where something failed beforehand.
                    // Known cases for signals failing at commit time include fe. https://jira.ez.no/browse/EZP-29333
                    if ($isCommitting && $e instanceof \RuntimeException && $e->getMessage() == 'There is no active transaction') {
                        // since all the migration steps succeeded and it was committed (because there was nothing to roll back),
                        // no use to mark it as failed...
                        $finalStatus = Migration::STATUS_DONE;
                        $finalMessage = 'An exception was thrown after committing, in file: ' .
                            $this->getFullExceptionMessage($error, true);
                        $error = new AfterMigrationExecutionException($finalMessage, 0, $e);
                    } else {
                        $finalMessage .= '. In addition, an exception was thrown while rolling back, in file ' .
                            $e->getFile() . ' line ' . $e->getLine() . ': ' . $e->getMessage();
                        $errorClass = get_class($error);
                        // we trust the constructor to be fine as error should only be a subclass of MigrationBundleException
                        $error = new $errorClass($finalMessage, $error->getCode(), $error->getPrevious());
                    }
                }
            }

        } finally {

            // save migration status

            $finalMessage = ($finalMessage != '' && $messageSuffix != '') ? $finalMessage . '. '. $messageSuffix : $finalMessage . $messageSuffix;

            try {
                $this->storageHandler->endMigration(new Migration(
                    $migration->name,
                    $migration->md5,
                    $migration->path,
                    $migration->executionDate,
                    $finalStatus,
                    $finalMessage
                ));
            } catch (\Exception $e) {
                // If we get here, the migration will be left in 'executing' state. It might be worth re-trying
                // to store its status for a couple of times, in case the error is transient, but that would
                // overcomplicate the business logic. So we at least give to the end user a specific error message

                if ($error) {
                    /// @todo use a more specific exception
                    $errorMessage = $finalMessage . '. In addition, an exception was thrown while saving migration status after its execution, in file ' .
                        $e->getFile() . ' line ' . $e->getLine() . ': ' . $e->getMessage();
                    $error = new MigrationBundleException($errorMessage, 0, $e);
                } else {
                    $errorMessage = 'An exception was thrown while saving migration status after its execution: ' .
                        $this->getFullExceptionMessage($e, true);
                    $error = new AfterMigrationExecutionException($errorMessage, 0, $e);
                }
            }

            if ($error) {
                throw $error;
            }
        }
    }

    /**
     * Note: previous API is kept for BC (subclasses reimplementing this method).
     * @param Migration $migration
     * @param array $migrationContext see executeMigration
     * @param array $forcedReferences Deprecated - use $migrationContext['forcedReferences']
     * @throws \Exception
     */
    public function resumeMigration(Migration $migration, $migrationContext = true, array $forcedReferences = array())
    {
        // BC: handling of legacy method call signature
        if (!is_array($migrationContext)) {
            $migrationContext = array(
                'useTransaction' => $migrationContext,
                'forcedReferences' => $forcedReferences,
            );
        } else {
            if (!is_array($forcedReferences) || count($forcedReferences)) {
                throw new MigrationBundleException("Invalid call to resumeMigration: argument types mismatch");
            }
        }

        if ($migration->status != Migration::STATUS_SUSPENDED) {
            throw new MigrationBundleException("Can not resume ".$this->getEntityName($migration)." '{$migration->name}': it is not in suspended status");
        }

        $migrationDefinitions = $this->getMigrationsDefinitions(array($migration->path));
        if (!count($migrationDefinitions)) {
            throw new MigrationBundleException("Can not resume ".$this->getEntityName($migration)." '{$migration->name}': its definition is missing");
        }

        $defs = $migrationDefinitions->getArrayCopy();
        $migrationDefinition = reset($defs);

        $migrationDefinition = $this->parseMigrationDefinition($migrationDefinition);
        if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
            throw new MigrationBundleException("Can not resume ".$this->getEntityName($migration)." '{$migration->name}': {$migrationDefinition->parsingError}");
        }

        // restore context
        $this->contextHandler->restoreCurrentContext($migration->name);
        if (!isset($this->migrationContext[$migration->name])) {
            throw new MigrationBundleException("Can not resume ".$this->getEntityName($migration)." '{$migration->name}': the stored context is missing");
        }
        $restoredContext = $this->migrationContext[$migration->name];
        if (!is_array($restoredContext) || !isset($restoredContext['context']) || !isset($restoredContext['step'])) {
            throw new MigrationBundleException("Can not resume ".$this->getEntityName($migration)." '{$migration->name}': the stored context is invalid");
        }

        // update migration status
        $migration = $this->storageHandler->resumeMigration($migration);

        // clean up restored context - ideally it should be in the same db transaction as the line above
        $this->contextHandler->deleteContext($migration->name);

        // and go
        // note: we store the current step counting starting at 1, but use offset starting at 0, hence the -1 here
        $this->executeMigrationInner($migration, $migrationDefinition, array_merge($restoredContext['context'], $migrationContext),
            $restoredContext['step'] - 1);
    }

    /**
     * @param string $defaultLanguageCode
     * @param string|int|false $adminLogin
     * @param bool|null $forceSigchildEnabled Doubly Deprecated!
     * @return array
     * @deprecated kept for BC
     */
    protected function migrationContextFromParameters($defaultLanguageCode = null, $adminLogin = null, $forceSigchildEnabled = null)
    {
        $properties = array();

        if ($defaultLanguageCode != null) {
            $properties['defaultLanguageCode'] = $defaultLanguageCode;
        }
        // nb: other parts of the codebase treat differently a false and null values for $properties['adminUserLogin']
        if ($adminLogin !== null) {
            $properties['adminUserLogin'] = $adminLogin;
        }
        //if ($forceSigchildEnabled !== null)
        //{
        //    $properties['forceSigchildEnabled'] = $forceSigchildEnabled;
        //}

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
        if (!isset($this->migrationContext[$migrationName]))
            return null;
        $context = $this->migrationContext[$migrationName];
        // avoid attempting to store the current outputInterface when saving the context
        if (isset($context['output'])) {
            unset($context['output']);
        }
        return $context;
    }

    /**
     * This gets called when we call $this->contextHandler->restoreCurrentContext().
     * @param string $migrationName
     * @param array $context
     */
    public function restoreContext($migrationName, array $context)
    {
        $this->migrationContext[$migrationName] = $context;
        if ($this->output) {
            $this->migrationContext['output'] = $this->output;
        }
    }

    protected function getEntityName($migration)
    {
        $array = explode('\\', get_class($migration));
        return strtolower(preg_replace('/Definition$/', '', end($array)));
    }
}

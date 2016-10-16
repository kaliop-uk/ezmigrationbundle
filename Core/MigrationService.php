<?php

namespace Kaliop\eZMigrationBundle\Core;

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
     * @param MigrationDefinition $migrationDefinition
     * @return Migration
     */
    public function skipMigration(MigrationDefinition $migrationDefinition)
    {
        return $this->storageHandler->skipMigration($migrationDefinition);
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

        $previousUserId = null;

        try {

            $i = 1;

            foreach($migrationDefinition->steps as $step) {
                // we validated the fact that we have a good executor at parsing time
                $executor = $this->executors[$step->type];
                $executor->execute($step);

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
                    // there might be workflows or other actions happening at commit time that fail if we are not admin
                    $previousUserId = $this->loginUser(self::ADMIN_USER_ID);
                    $this->repository->commit();
                    $this->loginUser($previousUserId);
                } catch(\RuntimeException $e) {
                    // At present time, the ez5 repo does not support nested commits. So if some migration step has
                    // committed already, we get an exception a this point. Extremely poor design, but what can we do ?

                    if ($previousUserId) {
                        $this->loginUser($previousUserId);
                    }

                    // we update the migration with the info about what just happened
                    $warning = 'An exception was thrown while committing, most likely due to some migration step being committed already: ' .
                        $this->getFullExceptionMessage($e) . ' in file ' . $e->getFile() . ' line ' . $e->getLine();
                    $this->storageHandler->endMigration(
                        new Migration(
                            $migration->name,
                            $migration->md5,
                            $migration->path,
                            $migration->executionDate,
                            $status,
                            $warning
                        ),
                        true
                    );
                }
            }

        } catch(\Exception $e) {

            $additionalError = '';

            if ($useTransaction) {
                try {
                    // there is no need to become admin here, at least in theory
                    $this->repository->rollBack();

                    // this should not happen, really, but we try to cater to the case where a commit() call above throws
                    // an unexpected exception
                    if ($previousUserId) {
                        $this->loginUser($previousUserId);
                    }

                } catch(\RuntimeException $e2) {
                    // at present time, the ez5 repo does not support nested commits. So if some migration step has
                    // committed already, we get an exception a this point. Extremely poor design, but what can we do ?
                    $additionalError = '. In addition, an exception was thrown while rolling back, most likely due to some migration step being committed already: ' .
                        $this->getFullExceptionMessage($e2) . ' in file ' . $e2->getFile() . ' line ' . $e2->getLine();
                } catch(\Exception $e3) {
                    $additionalError = '. In addition, an exception was thrown while rolling back: ' .
                        $this->getFullExceptionMessage($e3) . ' in file ' . $e3->getFile() . ' line ' . $e3->getLine();
                }
            }

            $errorMessage = $this->getFullExceptionMessage($e) . ' in file ' . $e->getFile() . ' line ' . $e->getLine() .
                $additionalError;

            // set migration as failed
            // NB: we use the 'force' flag here because we might be catching an exception happened during the call to
            // $this->repository->commit() above, in which case the Migration might be in the DB with a status 'done'
            $this->storageHandler->endMigration(
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

            throw new MigrationStepExecutionException($errorMessage, $i, $e);
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
            is_a($e, '\eZ\Publish\API\Repository\Exceptions\ContentTypeFieldValidationException') ||
            is_a($e, '\eZ\Publish\API\Repository\Exceptions\LimitationValidationException')
        ) {
            if (is_a($e, '\eZ\Publish\API\Repository\Exceptions\LimitationValidationException')) {
                $errorsArray = $e->getLimitationErrors();
                if ($errorsArray == null) {
                    return $message;
                }
                $errorsArray = array($errorsArray);
            } else {
                $errorsArray = $e->getFieldErrors();
            }

            foreach ($errorsArray as $errors) {

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

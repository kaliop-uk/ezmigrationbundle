<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use JmesPath\Env as JmesPath;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\MatcherInterface;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Symfony\Component\Yaml\Yaml;

/**
 * @property ReferenceResolverBagInterface $referenceResolver
 */
class MigrationDefinitionExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;
    use ReferenceSetterTrait;

    protected $supportedStepTypes = array('migration_definition');
    protected $supportedActions = array('generate', 'save', 'include');

    /** @var \Kaliop\eZMigrationBundle\Core\MigrationService $migrationService */
    protected $migrationService;

    public function __construct($migrationService, ReferenceResolverBagInterface $referenceResolver)
    {
        $this->migrationService = $migrationService;
        $this->referenceResolver = $referenceResolver;
    }

    /**
     * @param MigrationStep $step
     * @return mixed
     * @throws \Exception
     */
    public function execute(MigrationStep $step)
    {
        parent::execute($step);

        if (!isset($step->dsl['mode'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: missing 'mode'");
        }

        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new InvalidStepDefinitionException("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

        $this->skipStepIfNeeded($step);

        if ($action === 'include') {
            // we can not use a keyword as method name
            $action = 'run';
        }

        return $this->$action($step->dsl, $step->context);
    }

    public function run($dsl, $context)
    {
        if (!isset($dsl['file'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: miss 'file'");
        }
        $fileName = $this->resolveReference($dsl['file']);

        // default format: path is relative to the current mig dir
        $realFilePath = dirname($context['path']) . $fileName;
        // but we support as well absolute paths
        if (!is_file($realFilePath) && is_file($fileName)) {
            $realFilePath = $fileName;
        }

        $migrationDefinitions = $this->migrationService->getMigrationsDefinitions(array($realFilePath));
        if (!count($migrationDefinitions)) {
            throw new InvalidStepDefinitionException("Invalid step definition: '$fileName' is not a valid migration definition");
        }

        // avoid overwriting the 'current migration definition file path' for the included definition
        unset($context['path']);

        /// @todo can we could have the included migration's steps be printed as 1.1, 1.2 etc...
        /// @todo we could return the result of the included migration's last step
        foreach($migrationDefinitions as $migrationDefinition) {
            $this->migrationService->executeMigration($migrationDefinition, $context);
        }

        return true;
    }

    /**
     * @todo allow to save to disk
     * @param array $dsl
     * @param array $context
     * @return array
     * @throws \Exception
     */
    protected function generate($dsl, $context)
    {
        if (!isset($dsl['migration_type'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: miss 'migration_type'");
        }
        $migrationType = $this->resolveReference($dsl['migration_type']);
        if (!isset($dsl['migration_mode'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: miss 'migration_mode'");
        }
        $migrationMode = $this->resolveReference($dsl['migration_mode']);
        if (!isset($dsl['match']) || !is_array($dsl['match'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: miss 'match' to determine what to generate migration definition for");
        }
        $match = $dsl['match'];

        $executors = $this->getGeneratingExecutors();
        if (!in_array($migrationType, $executors)) {
            throw new InvalidStepDefinitionException("It is not possible to generate a migration of type '$migrationType': executor not found or not a generator");
        }
        /** @var MigrationGeneratorInterface $executor */
        $executor = $this->migrationService->getExecutor($migrationType);

        if (isset($dsl['lang']) && $dsl['lang'] != '') {
            $context['defaultLanguageCode'] = $this->resolveReference($dsl['lang']);
        }

        // in case the executor does different things based on extra information present in the step definition
        $context['step'] = $dsl;

        // q: should we use resolveReferenceRecursively for $match['value']?
        $matchCondition = array($this->resolveReference($match['type']) => $this->resolveReference($match['value']));
        if (isset($match['except']) && $match['except']) {
            $matchCondition = array(MatcherInterface::MATCH_NOT => $matchCondition);
        }

        $result = $executor->generateMigration($matchCondition, $migrationMode, $context);

        if (isset($dsl['file'])) {

            $fileName = $this->resolveReference($dsl['file']);

            $this->saveDefinition($result, $fileName);
        }

        $this->setReferences($result, $dsl);

        return $result;
    }

    public function save($dsl, $context)
    {
        if (!isset($dsl['migration_steps'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: miss 'migration_steps'");
        }
        if (!isset($dsl['file'])) {
            throw new InvalidStepDefinitionException("Invalid step definition: miss 'file'");
        }

        if (is_string($dsl['migration_steps'])) {
            $definition = $this->resolveReference($dsl['migration_steps']);
        } else {
            $definition = $dsl['migration_steps'];
        }

        /// @todo allow resolving references within texts, not only as full value
        $definition = $this->resolveReferencesRecursively($definition);

        $fileName = $this->resolveReference($dsl['file']);

        $this->saveDefinition($definition, $fileName);

        /// @todo what to allow setting refs to ?

        /// @todo what to return ?
    }

    /// @todo move to a Loader service
    protected function saveDefinition($definition, $fileName)
    {
        $ext = pathinfo(basename($fileName), PATHINFO_EXTENSION);

        switch ($ext) {
            case 'yml':
            case 'yaml':
                /// @todo use Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE option if it is supported
                $code = Yaml::dump($definition, 5);
                break;
            case 'json':
                $code = json_encode($definition, JSON_PRETTY_PRINT);
                break;
            default:
                throw new InvalidStepDefinitionException("Can not save migration definition to a file of type '$ext'");
        }

        $dir = dirname($fileName);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!file_put_contents($fileName, $code)) {
            throw new MigrationBundleException("Failed saving migration definition to file '$fileName'");
        }
    }

    protected function setReferences($result, $dsl)
    {
        if (!array_key_exists('references', $dsl) || !count($dsl['references'])) {
            return false;
        }

        foreach ($dsl['references'] as $key => $reference) {
            // BC
            if (is_array($reference) && isset($reference['json_path']) && !isset($reference['attribute'] )) {
                $reference['attribute'] = $reference['json_path'];
            }
            $reference = $this->parseReferenceDefinition($key, $reference);
            switch ($reference['attribute']) {
                case 'definition':
                    $value = $result;
                    break;
                default:
                    $value = JmesPath::search($reference['attribute'], $result);
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }

            $this->addReference($reference['identifier'], $value, $overwrite);
        }

        return true;
    }

    /**
     * @todo cache this for faster access
     * @return string[]
     */
    protected function getGeneratingExecutors()
    {
        $migrationService = $this->migrationService;
        $executors = $migrationService->listExecutors();
        foreach ($executors as $key => $name) {
            $executor = $migrationService->getExecutor($name);
            if (!$executor instanceof MigrationGeneratorInterface) {
                unset($executors[$key]);
            }
        }
        return $executors;
    }
}

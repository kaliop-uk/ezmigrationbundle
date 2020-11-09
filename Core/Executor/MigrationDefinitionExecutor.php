<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use JmesPath\Env as JmesPath;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\MatcherInterface;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Symfony\Component\Yaml\Yaml;

class MigrationDefinitionExecutor extends AbstractExecutor
{
    use IgnorableStepExecutorTrait;

    protected $supportedStepTypes = array('migration_definition');
    protected $supportedActions = array('generate', 'save');

    /** @var \Kaliop\eZMigrationBundle\Core\MigrationService $migrationService */
    protected $migrationService;
    /** @var ReferenceResolverBagInterface $referenceResolver */
    protected $referenceResolver;

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

        return $this->$action($step->dsl, $step->context);
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
            throw new \Exception("Invalid step definition: miss 'migration_type'");
        }
        $migrationType = $this->referenceResolver->resolveReference($dsl['migration_type']);
        if (!isset($dsl['migration_mode'])) {
            throw new \Exception("Invalid step definition: miss 'migration_mode'");
        }
        $migrationMode = $this->referenceResolver->resolveReference($dsl['migration_mode']);
        if (!isset($dsl['match']) || !is_array($dsl['match'])) {
            throw new \Exception("Invalid step definition: miss 'match' to determine what to generate migration definition for");
        }
        $match = $dsl['match'];

        $executors = $this->getGeneratingExecutors();
        if (!in_array($migrationType, $executors)) {
            throw new \Exception("It is not possible to generate a migration of type '$migrationType': executor not found or not a generator");
        }
        /** @var MigrationGeneratorInterface $executor */
        $executor = $this->migrationService->getExecutor($migrationType);

        if (isset($dsl['lang']) && $dsl['lang'] != '') {
            $context['defaultLanguageCode'] = $this->referenceResolver->resolveReference($dsl['lang']);
        }

        // in case the executor does different things based on extra information present in the step definition
        $context['step'] = $dsl;

        $matchCondition = array($this->referenceResolver->resolveReference($match['type']) => $this->referenceResolver->resolveReference($match['value']));
        if (isset($match['except']) && $match['except']) {
            $matchCondition = array(MatcherInterface::MATCH_NOT => $matchCondition);
        }

        $result = $executor->generateMigration($matchCondition, $migrationMode, $context);

        if (isset($dsl['file'])) {

            $fileName = $this->referenceResolver->resolveReference($dsl['file']);

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
            $definition = $this->referenceResolver->resolveReference($dsl['migration_steps']);
        } else {
            $definition = $dsl['migration_steps'];
        }

        $definition = $this->resolveReferencesRecursively($definition);

        $fileName = $this->referenceResolver->resolveReference($dsl['file']);

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
                throw new \Exception("Can not save migration definition to a file of type '$ext'");
        }

        $dir = dirname($fileName);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!file_put_contents($fileName, $code)) {
            throw new \Exception("Failed saving migration definition to file '$fileName'");
        }
    }

    protected function setReferences($result, $dsl)
    {
        if (!array_key_exists('references', $dsl)) {
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

            $this->referenceResolver->addReference($reference['identifier'], $value, $overwrite);
        }
    }

    /**
     * @todo cache this for faster access
     * @return string[]
     */
    protected function getGeneratingExecutors()
    {
        $migrationService = $this->migrationService;
        $executors = $migrationService->listExecutors();
        foreach($executors as $key => $name) {
            $executor = $migrationService->getExecutor($name);
            if (!$executor instanceof MigrationGeneratorInterface) {
                unset($executors[$key]);
            }
        }
        return $executors;
    }

    /**
     * @todo should be moved into the reference resolver classes
     * @todo allow resolving references within texts, not only as full value
     */
    protected function resolveReferencesRecursively($match)
    {
        if (is_array($match)) {
            foreach ($match as $condition => $values) {
                $match[$condition] = $this->resolveReferencesRecursively($values);
            }
            return $match;
        } else {
            return $this->referenceResolver->resolveReference($match);
        }
    }
}

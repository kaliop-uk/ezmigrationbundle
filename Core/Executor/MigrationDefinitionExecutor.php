<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\MatcherInterface;
use Kaliop\eZMigrationBundle\API\ReferenceBagInterface;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use JmesPath\Env as JmesPath;

class MigrationDefinitionExecutor extends AbstractExecutor
{
    protected $supportedStepTypes = array('migration_definition');
    protected $supportedActions = array('generate');

    /** @var \Kaliop\eZMigrationBundle\Core\MigrationService $migrationService */
    protected $migrationService;
    /** @var ReferenceBagInterface $referenceResolver */
    protected $referenceResolver;

    public function __construct($migrationService, ReferenceBagInterface $referenceResolver)
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
            throw new \Exception("Invalid step definition: missing 'mode'");
        }

        $action = $step->dsl['mode'];

        if (!in_array($action, $this->supportedActions)) {
            throw new \Exception("Invalid step definition: value '$action' is not allowed for 'mode'");
        }

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
        $migrationType = $dsl['migration_type'];
        if (!isset($dsl['migration_mode'])) {
            throw new \Exception("Invalid step definition: miss 'migration_mode'");
        }
        $migrationMode = $dsl['migration_mode'];
        if (!isset($dsl['match']) || !is_array($dsl['match'])) {
            throw new \Exception("Invalid step definition: miss 'match' to determine what to generate migration definition for");
        }
        $match = $dsl['match'];

        $executors = $this->getGeneratingExecutors();
        if (!in_array($migrationType, $executors)) {
            throw new \Exception("It is not possible to generate a migration of type '$migrationType': executor not found or not a generator");
        }
        $executor = $this->migrationService->getExecutor($migrationType);

        $context = array();
        if (isset($dsl['lang']) && $dsl['lang'] != '') {
            $context['defaultLanguageCode'] = $dsl['lang'];
        }

        $matchCondition = array($match['type'] => $match['value']);
        if (isset($match['except']) && $match['except']) {
            $matchCondition = array(MatcherInterface::MATCH_NOT => $matchCondition);
        }
        $result = $executor->generateMigration($matchCondition, $migrationMode, $context);

        $this->setReferences($result, $dsl);

        return $result;
    }

    protected function setReferences($result, $dsl)
    {
        if (!array_key_exists('references', $dsl)) {
            return false;
        }

        foreach ($dsl['references'] as $reference) {
            if (!isset($reference['json_path'])) {
                throw new \InvalidArgumentException('MigrationDefinition Executor does not support setting references if not using a json_path expression');
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            $value = JmesPath::search($reference['json_path'], $result);
            $this->referenceResolver->addReference($reference['identifier'], $value, $overwrite);
        }
    }

    /**
     * @todo cache this for faster access
     * @return array
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
}

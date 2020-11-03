<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ExecutorInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationAbortedException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationSuspendedException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

class MigrationExecutor extends AbstractExecutor
{
    protected $supportedStepTypes = array('migration');
    protected $supportedActions = array('cancel', 'fail', 'suspend', 'sleep');

    protected $referenceMatcher;
    protected $referenceResolver;
    protected $contentManager;
    protected $locationManager;
    protected $contentTypeManager;

    public function __construct($referenceMatcher, ReferenceResolverInterface $referenceResolver, ExecutorInterface $contentManager, ExecutorInterface $locationManager, ExecutorInterface $contentTypeManager)
    {
        $this->referenceMatcher = $referenceMatcher;
        $this->referenceResolver = $referenceResolver;
        $this->contentManager = $contentManager;
        $this->locationManager = $locationManager;
        $this->contentTypeManager = $contentTypeManager;
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

        return $this->$action($step->dsl, $step->context);
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return true
     * @throws \Exception
     */
    protected function cancel($dsl, $context)
    {
        $message = isset($dsl['message']) ? $dsl['message'] : '';

        if (isset($dsl['if'])) {
            if (!$this->matchConditions($dsl['if'])) {
                // q: return timestamp, matched condition or ... ?
                return true;
            }
        }

        throw new MigrationAbortedException($message);
    }

    protected function fail($dsl, $context)
    {
        $message = isset($dsl['message']) ? $dsl['message'] : '';

        if (isset($dsl['if'])) {
            if (!$this->matchConditions($dsl['if'])) {
                // q: return timestamp, matched condition or ... ?
                return true;
            }
        }

        throw new MigrationAbortedException($message, Migration::STATUS_FAILED);
    }

    /**
     * @param array $dsl
     * @param array $context
     * @return true
     * @throws \Exception
     */
    protected function suspend($dsl, $context)
    {
        $message = isset($dsl['message']) ? $dsl['message'] : '';

        if (!isset($dsl['until'])) {
            throw new InvalidStepDefinitionException("An until condition is required to suspend a migration");
        }

        if (isset($dsl['load'])) {
            $this->loadEntity($dsl['load'], $context);
        }

        if ($this->matchSuspend($dsl['until'])) {
            // the time has come to resume!
            // q: return timestamp, matched condition or ... ?
            return true;
        }

        throw new MigrationSuspendedException($message);
    }

    protected function sleep($dsl, $context)
    {
        if (!isset($dsl['seconds'])) {
            throw new InvalidStepDefinitionException("A 'seconds' element is required when putting a migration to sleep");
        }

        sleep($dsl['seconds']);
        return true;
    }

    protected function loadEntity($dsl, $context)
    {
        if (!isset($dsl['type']) || !isset($dsl['match'])) {
            throw new InvalidStepDefinitionException("A 'type' and a 'match' are required to load entities when suspending a migration");
        }

        $dsl['mode'] = 'load';
        // be kind to users and allow them not to specify this explicitly
        if (isset($dsl['references'])) {
            foreach($dsl['references'] as &$refDef) {
                $refDef['overwrite'] = true;
            }
        }
        $step = new MigrationStep($dsl['type'], $dsl, $context);

        switch($dsl['type']) {
            case 'content':
                return $this->contentManager->execute($step);
            case 'location':
                return $this->locationManager->execute($step);
            case 'content_type':
                return $this->contentTypeManager->execute($step);
        }
    }

    protected function matchConditions($conditions)
    {
        $match = $this->referenceMatcher->match($conditions);
        if ($match instanceof \ArrayObject) {
            $match = $match->getArrayCopy();
        }
        return reset($match);
    }

    protected function matchSuspend($conditions)
    {
        foreach ($conditions as $key => $values) {

            switch ($key) {
                case 'date':
                    return time() >= $this->referenceResolver->resolveReference($values);

                case 'match':
                    return $this->matchConditions($values);

                default:
                    throw new InvalidStepDefinitionException("Unknown until condition: '$key' when suspending a migration ".var_export($conditions, true));
            }
        }
    }
}

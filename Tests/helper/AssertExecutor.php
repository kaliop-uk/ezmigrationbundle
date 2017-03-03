<?php

namespace Kaliop\eZMigrationBundle\Tests\helper;

use Kaliop\eZMigrationBundle\Core\Executor\AbstractExecutor;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

class AssertExecutor extends  AbstractExecutor
{
    protected $supportedStepTypes = array('assert');
    protected $supportedActions = array('reference'/*, 'generated'*/);

    /** @var ReferenceResolverInterface $referenceResolver */
    protected $referenceResolver;

    public function __construct(ReferenceResolverInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
    }

    public function execute(MigrationStep $step)
    {
        parent::execute($step);

        if (!isset($step->dsl['target'])) {
            throw new \Exception("Invalid step definition: missing 'target'");
        }

        $action = $step->dsl['target'];

        if (!in_array($action, $this->supportedActions)) {
            throw new \Exception("Invalid step definition: value '$action' is not allowed for 'target'");
        }

        if (!isset($step->dsl['test']) || !is_array($step->dsl['test'])) {
            throw new \Exception("Invalid step definition: missing 'test'");
        }

        $action = 'assert' . ucfirst($action);
        return $this->$action($step->dsl, $step->context);
    }

    protected function assertReference($dsl, $context)
    {
        if (!isset($dsl['identifier'])) {
            throw new \Exception("Invalid step definition: miss 'identifier' to check reference value");
        }
        if (!$this->referenceResolver->isReference($dsl['identifier'])) {
            throw new \Exception("Invalid step definition: identifier '{$dsl['identifier']}' is not a reference");
        }
        $value = $this->referenceResolver->resolveReference($dsl['identifier']);

        $this->validate($value, $dsl['test']);
    }

    /*protected function assertGenerated($dsl, $context)
    {
    }*/

    protected function validate($value, array $condition)
    {
        $targetValue = reset($condition);
        $flip = array_flip($condition);
        $testCondition = reset($flip);
        $testMethod = 'assert' . ucfirst($testCondition);
        if (! is_callable(array('PHPUnit_Framework_Assert', $testMethod))) {
            throw new \Exception("Invalid step definition: invalid test condition '$testCondition'");
        }

        call_user_func(array('PHPUnit_Framework_Assert', $testMethod), $targetValue, $value);
    }
}

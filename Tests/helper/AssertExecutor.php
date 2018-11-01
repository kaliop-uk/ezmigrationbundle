<?php

namespace Kaliop\eZMigrationBundle\Tests\helper;

use Kaliop\eZMigrationBundle\Core\Executor\AbstractExecutor;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

class AssertExecutor extends AbstractExecutor
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

    /**
     * @todo !important switch to using symfony/validator for uniformity with the rest of the codebase ?
     *       This would allow us to move the 'assert' executor outside of test code...
     * @param mixed $value
     * @param array $condition
     * @throws \Exception
     */
    protected function validate($value, array $condition)
    {
        // we do resolve references as well in the value to check against
        $targetValue = reset($condition);
        $targetValue = $this->referenceResolver->resolveReference($targetValue);
        $testCondition = key($condition);
        $testMethod = 'assert' . ucfirst($testCondition);

        switch (true) {
            case method_exists('PHPUnit_Runner_Version','id'):
                $assertClass = 'PHPUnit_Framework_Assert';
                break;
            case method_exists('PHPUnit\Runner\Version','id'):
                $assertClass = 'PHPUnit\Framework\Assert';
                break;
            default:
                throw new \Exception("Unable to find PHPUnit");
        }

        if (!is_callable(array($assertClass, $testMethod))) {
            throw new \Exception("Invalid step definition: invalid test condition '$testCondition'");
        }

        /// @todo this prints out messages similar to 'Failed asserting that two strings are equal.' but not the strings themselves.  We could catch and decode the exception...
        call_user_func(array($assertClass, $testMethod), $targetValue, $value);
    }
}

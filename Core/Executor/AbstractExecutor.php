<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ExecutorInterface;

abstract class AbstractExecutor implements ExecutorInterface
{
    protected $supportedStepTypes = array();

    public function supportedTypes()
    {
        return $this->supportedStepTypes;
    }

    /**
     * IT IS MANDATORY TO OVERRIDE THIS IN SUBCLASSES
     * @param MigrationStep $step
     * @return mixed
     * @throws \Exception
     */
    public function execute(MigrationStep $step)
    {
        if (!in_array($step->type, $this->supportedStepTypes)) {
            throw new \Exception("Something is wrong! Executor can not work on step of type '{$step->type}'");
        }
    }
}

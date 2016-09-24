<?php

namespace Kaliop\eZMigrationBundle\API\Event;

use Symfony\Component\EventDispatcher\Event;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ExecutorInterface;

class BeforeStepExecutionEvent extends Event
{
    protected $step;
    protected $executor;

    public function __construct(MigrationStep $step, ExecutorInterface $executor)
    {
        $this->step = $step;
        $this->executor = $executor;
    }

    /**
     * @return MigrationStep
     */
    public function getStep()
    {
        return $this->step;
    }

    /**
     * @return mixed
     */
    public function getExecutor()
    {
        return $this->executor;
    }
}

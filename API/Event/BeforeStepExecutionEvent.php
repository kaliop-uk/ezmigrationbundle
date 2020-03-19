<?php

namespace Kaliop\eZMigrationBundle\API\Event;

use Symfony\Contracts\EventDispatcher\Event;
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

    /**
     * Here be dragons
     * @param ExecutorInterface $executor
     */
    public function replaceExecutor(ExecutorInterface $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Lasciate ogni speranza, voi ch'entrate
     * @param MigrationStep $step
     */
    public function replaceStep(MigrationStep $step)
    {
        $this->step = $step;
    }
}

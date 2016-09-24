<?php

namespace Kaliop\eZMigrationBundle\API\Event;

use Symfony\Component\EventDispatcher\Event;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

class StepExecutedEvent extends Event
{
    protected $step;
    protected $result;

    public function __construct(MigrationStep $step, $result)
    {
        $this->step = $step;
        $this->result = $result;
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
    public function getResult()
    {
        return $this->result;
    }
}
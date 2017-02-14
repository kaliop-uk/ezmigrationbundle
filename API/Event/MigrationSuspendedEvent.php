<?php

namespace Kaliop\eZMigrationBundle\API\Event;

use Symfony\Component\EventDispatcher\Event;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\Exception\MigrationSuspendedException;

class MigrationSuspendedEvent extends Event
{
    protected $step;
    protected $exception;

    public function __construct(MigrationStep $step, MigrationSuspendedException $exception)
    {
        $this->step = $step;
        $this->exception = $exception;
    }

    /**
     * @return MigrationStep
     */
    public function getStep()
    {
        return $this->step;
    }

    /**
     * @return MigrationSuspendedException
     */
    public function getException()
    {
        return $this->exception;
    }
}

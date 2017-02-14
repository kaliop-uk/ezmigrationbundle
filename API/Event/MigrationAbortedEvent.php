<?php

namespace Kaliop\eZMigrationBundle\API\Event;

use Symfony\Component\EventDispatcher\Event;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\Exception\MigrationAbortedException;

class MigrationAbortedEvent extends Event
{
    protected $step;
    protected $exception;

    public function __construct(MigrationStep $step, MigrationAbortedException $exception)
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
     * @return MigrationAbortedException
     */
    public function getException()
    {
        return $this->exception;
    }
}

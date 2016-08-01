<?php

namespace Kaliop\eZMigrationBundle\API\Value;

/**
 * @property-read string $name
 * @property-read string $md5 of the original definition file
 * @property-read string $path
 * @property-read int $executionDate timestamp
 * @property-read integer $status
 * @property-read string $executionError
 */
class Migration extends AbstractValue
{
    const STATUS_TODO = 0;
    const STATUS_STARTED = 1;
    const STATUS_DONE = 2;
    const STATUS_FAILED = 3;
    // the 2 below are not yet supported
    const STATUS_SKIPPED = 4;
    const STATUS_PARTIALLY_DONE = 5;

    protected $name;
    protected $md5;
    protected $path;
    protected $executionDate;
    protected $status;
    protected $executionError;

    /**
     * @param string $name
     * @param string md5 checksum of the migration definition file
     * @param string $path
     * @param int $executionDate timestamp
     * @param int $status
     */
    function __construct($name, $md5, $path, $executionDate = null, $status = 0, $executionError = null)
    {
        $this->name = $name;
        $this->md5 = $md5;
        $this->path = $path;
        $this->executionDate = $executionDate;
        $this->status = $status;
        $this->executionError = $executionError;
    }
}

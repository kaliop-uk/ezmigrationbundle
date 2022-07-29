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
    const STATUS_SKIPPED = 4;
    const STATUS_SUSPENDED = 6;
    // the ones below are not yet supported
    const STATUS_PARTIALLY_DONE = 5;

    /** @var string */
    protected $name;
    /** @var string */
    protected $md5;
    /** @var string full path including the filename, relative to the app's root dir if contained within it */
    protected $path;
    /** @var int|null timestamp */
    protected $executionDate;
    /** @var int */
    protected $status;
    protected $executionError;

    /**
     * @param string $name
     * @param string $md5 checksum of the migration definition file
     * @param string $path
     * @param int $executionDate timestamp
     * @param int $status
     * @param $executionError
     */
    public function __construct($name, $md5, $path, $executionDate = null, $status = 0, $executionError = null)
    {
        $this->name = $name;
        $this->md5 = $md5;
        $this->path = $path;
        $this->executionDate = $executionDate;
        $this->status = $status;
        $this->executionError = $executionError;
    }
}

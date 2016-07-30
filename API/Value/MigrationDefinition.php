<?php

namespace Kaliop\eZMigrationBundle\API\Value;

use Kaliop\eZMigrationBundle\API\Collection\MigrationStepsCollection;

/**
 * @property-read string $name
 * @property-read string $path
 * @property-read string $rawDefinition
 * @property-read integer $status
 * @property-read MigrationStepsCollection $steps
 * @property-read string $parsingError
 */
class MigrationDefinition extends AbstractValue
{
    const STATUS_TO_PARSE = 0;
    const STATUS_PARSED = 1;
    const STATUS_INVALID = 2;

    protected $name;
    protected $path;
    protected $rawDefinition;
    protected $status = 0;
    protected $steps;
    protected $parsingError;

    /**
     * @param string $name
     * @param string $path
     * @param string $rawDefinition
     * @param int $status
     * @param MigrationStep[] $steps
     * @param string $parsingError
     */
    function __construct($name, $path, $rawDefinition, $status = 0, array $steps=array(), $parsingError = null)
    {
        $this->name = $name;
        $this->path = $path;
        $this->rawDefinition = $rawDefinition;
        $this->status = $status;
        $this->steps = new MigrationStepsCollection($steps);
        $this->parsingError = $parsingError;
    }
}

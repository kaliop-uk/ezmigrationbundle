<?php

namespace Kaliop\eZMigrationBundle\API\Value;

use Kaliop\eZMigrationBundle\API\Collection\MigrationStepsCollection;

class MigrationDefinition
{
    protected $name;
    protected $steps;

    /**
     * MigrationDefinition constructor.
     * @param $name
     * @param MigrationStep[] $steps
     */
    function __construct($name, array $steps)
    {
        $this->name = $name;
        $this->steps = new MigrationStepsCollection($steps);
    }
}

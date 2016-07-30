<?php

namespace Kaliop\eZMigrationBundle\API\Value;

/**
 * @property-read string $type
 * @property-read array $dsl
 */
class MigrationStep extends AbstractValue
{
    protected $type;
    protected $dsl;

    function __construct($type, array $dsl = array())
    {
        $this->type = $type;
        $this->dsl = $dsl;
    }
}

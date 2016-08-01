<?php

namespace Kaliop\eZMigrationBundle\API\Value;

/**
 * @property-read string $type
 * @property-read array $dsl
 * @property-read array $context
 */
class MigrationStep extends AbstractValue
{
    protected $type;
    protected $dsl;
    protected $context;

    function __construct($type, array $dsl = array(), array $context = array())
    {
        $this->type = $type;
        $this->dsl = $dsl;
        $this->context = $context;
    }
}

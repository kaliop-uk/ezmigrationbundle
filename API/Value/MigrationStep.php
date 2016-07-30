<?php

namespace Kaliop\eZMigrationBundle\API\Value;

class MigrationStep
{
    protected $type;
    protected $dsl;

    function __construct($type, array $dsl = array())
    {
        $this->type = $type;
        $this->dsl = $dsl;
    }
}

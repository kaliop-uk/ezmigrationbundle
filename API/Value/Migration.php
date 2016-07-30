<?php

namespace Kaliop\eZMigrationBundle\API\Value;

class Migration
{
    protected $name;
    protected $bundle;
    protected $execution_date;
    protected $status;

    function __construct($name, $bundle, $execution_date, $status)
    {
        $this->name = $name;
        $this->bundle = $bundle;
        $this->execution_date = $execution_date;
        $this->status = $status;
    }
}

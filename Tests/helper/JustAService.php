<?php

namespace Kaliop\eZMigrationBundle\Tests\helper;

use Kaliop\eZMigrationBundle\API\Exception\MigrationAbortedException;

class JustAService
{
    public function echoBack($string)
    {
        return $string;
    }

    public function throwMigrationAbortedException($msg)
    {
        throw  new MigrationAbortedException($msg);
    }
}

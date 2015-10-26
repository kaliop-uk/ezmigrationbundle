<?php

namespace Kaliop\Migration\Tests;


use Doctrine\DBAL\DriverManager;

class BundleMigrationDBTestCase extends BundleMigrationTestCase
{
    protected $connection;

    public function setUp()
    {
        $this->connection = DriverManager::getConnection( array( 'driver' => 'pdo_sqlite', 'memory' => true ) );
    }
}
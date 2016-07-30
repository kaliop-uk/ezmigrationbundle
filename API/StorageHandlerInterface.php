<?php

namespace Kaliop\eZMigrationBundle\API;

use Kaliop\eZMigrationBundle\API\Collection\MigrationCollection;

/**
 * Implemented by classes which store details of the executed migrations
 */
interface StorageHandlerInterface
{
    /**
     * @return MigrationCollection
     */
    public function loadMigrations();
}

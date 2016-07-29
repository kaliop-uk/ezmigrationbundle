<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Interface for all complex field creation classes.
 */
interface ComplexFieldInterface
{
    /**
     * Create a non primitive field value
     *
     * @return mixed
     */
    public function createValue();
}
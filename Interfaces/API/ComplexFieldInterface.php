<?php
/**
 * Interface for all complex field creation classes.
 */

namespace Kaliop\eZMigrationBundle\Interfaces\API;


interface ComplexFieldInterface
{
    /**
     * Create a non primitive field value
     *
     * @return mixed
     */
    public function createValue();
}
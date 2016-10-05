<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Interface for all complex field creation classes.
 */
interface ComplexFieldInterface
{
    /**
     * Return a non primitive field value - or a primitive field value after further transformation
     *
     * @param $fieldValueArray The definition of the field value, as used in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return mixed
     */
    public function createValue($fieldValueArray, array $context = array());
}

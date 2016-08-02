<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Interface for all complex field creation classes.
 */
interface ComplexFieldInterface
{
    /**
     * Return a non primitive field value
     * @param array $fieldValueArray The definition of teh field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return mixed
     */
    public function createValue(array $fieldValueArray, array $context = array());
}
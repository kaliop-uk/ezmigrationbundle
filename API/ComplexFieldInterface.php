<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Interface for all complex field creation classes.
 */
interface ComplexFieldInterface
{
    /**
     * Return a non primitive field value - or a primitive field value with custom transformation
     *
     * @param $fieldValue The definition of the field value, as used in the yml file. Can be an array or a scalar value...
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return mixed the object / array / scalar which will be passed to the setField() call of a content update or creation structure
     */
    public function createValue($fieldValue, array $context = array());
}

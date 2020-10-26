<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Interface for all classes dealing with the conversion of field values from/to hash representation to/from repo values
 */
interface FieldValueConverterInterface extends FieldValueImporterInterface
{
    /**
     * Converts the Content Field value as gotten from the migration definition into something the repo can understand
     *
     * @param mixed $fieldValue The Content Field value hash as gotten from the repo
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return mixed the array / scalar / usable as field value in a migration definition
     */
    public function fieldValueToHash($fieldValue, array $context = array());
}

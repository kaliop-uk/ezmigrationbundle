<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Interface for all classes dealing with the conversion of field values from hash representation to repo values
 */
interface FieldValueImporterInterface
{
    /**
     * Converts the Content Field value as gotten from the migration definition into something the repo can understand
     *
     * @param mixed $fieldHash The Content Field value hash as gotten from the migration definition
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return mixed the array / scalar / obj usable as field value in a Content create/update struct
     */
    public function hashToFieldValue($fieldHash, array $context = array());
}

<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use eZ\Publish\Core\FieldType\Checkbox\Value as CheckboxValue;
use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;

class EzBoolean extends AbstractFieldHandler implements FieldValueImporterInterface
{
    /**
     * Creates a value object to use as the field value when setting a boolean field type.
     *
     * @param $fieldValue int:string:bool should all work
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return CheckboxValue
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        return new CheckboxValue(($fieldValue == 1) ? true : false);
    }
}

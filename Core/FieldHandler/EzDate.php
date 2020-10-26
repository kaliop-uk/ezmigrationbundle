<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;

class EzDate extends AbstractFieldHandler implements FieldValueConverterInterface
{
    /**
     * Creates a value object to use as the field value when setting a boolean field type.
     *
     * @param int|string|null $fieldValue use a timestamp; if as string, prepend it with @ sign
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return int|string
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        return $fieldValue;
    }

    /**
     * @param \eZ\Publish\Core\FieldType\Date\Value $fieldValue
     * @param array $context
     * @return int|null timestamp
     */
    public function fieldValueToHash($fieldValue, array $context = array())
    {
        $date = $fieldValue->date;
        return $date == null ? null : $date->getTimestamp();
    }
}

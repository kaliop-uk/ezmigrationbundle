<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use Kaliop\eZMigrationBundle\API\FieldDefinitionConverterInterface;

class EzDateAndTime extends AbstractFieldHandler implements FieldValueConverterInterface, FieldDefinitionConverterInterface
{
    /**
     * @param int|string|null $fieldValue use a timestamp; if as string, prepend it with @ sign
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return int|string
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        return $fieldValue;
    }

    /**
     * @param \eZ\Publish\Core\FieldType\DateAndTime\Value $fieldValue
     * @param array $context
     * @return int|null timestamp
     */
    public function fieldValueToHash($fieldValue, array $context = array())
    {
        $date = $fieldValue->value;
        return $date == null ? null : $date->getTimestamp();
    }

    public function fieldSettingsToHash($settingsValue, array $context = array())
    {
        if (is_array($settingsValue) && isset($settingsValue['dateInterval']) && is_object($settingsValue['dateInterval'])) {
            $settingsValue['dateInterval'] = $this->format($settingsValue['dateInterval']);
        }
        return $settingsValue;
    }

    public function hashToFieldSettings($settingsHash, array $context = array())
    {
        if (is_array($settingsHash) && isset($settingsHash['dateInterval']) && is_string($settingsHash['dateInterval'])) {
            // ISO-8601 format, loose matching because I am lazy
            if (preg_match('/^P[0-9YMDWTHS]+$/', $settingsHash['dateInterval'])) {
                $settingsHash['dateInterval'] = new \DateInterval($settingsHash['dateInterval']);
            } else {
                $settingsHash['dateInterval'] = \DateInterval::createFromDateString($settingsHash['dateInterval']);
            }
        }
        return $settingsHash;
    }

    /**
     * @param \DateInterval $dateInterval
     * @return string
     * @todo add support for DateInterval::$invert
     */
    protected function format(\DateInterval $dateInterval)
    {
        $format = array();
        if (0 != $dateInterval->y) {
            $format[] = $dateInterval->y.' years';
        }
        if (0 != $dateInterval->m) {
            $format[] = $dateInterval->m.' months';
        }
        if (0 != $dateInterval->d) {
            $format[] = $dateInterval->d.' days';
        }
        //if (0 < $dateInterval->h || 0 < $dateInterval->i || 0 < $dateInterval->s) {
        //    $format .= 'T';
        //}
        if (0 != $dateInterval->h) {
            $format[] = $dateInterval->h.' hours';
        }
        if (0 != $dateInterval->i) {
            $format[] = $dateInterval->i.' minutes';
        }
        if (0 != $dateInterval->s) {
            $format[] = $dateInterval->s.' seconds';
        }
        return implode( ', ', $format);
    }
}

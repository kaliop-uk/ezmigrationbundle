<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;
use eZ\Publish\API\Repository\FieldTypeService;
use Kaliop\eZMigrationBundle\API\FieldSettingsHandlerInterface;

class ComplexFieldManager
{
    /** @var ComplexFieldInterface[][] */
    protected $fieldTypeMap;
    protected $fieldTypeService;

    public function __construct(FieldTypeService $fieldTypeService)
    {
        $this->fieldTypeService = $fieldTypeService;
    }

    /**
     * @param ComplexFieldInterface $complexField
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     */
    public function addComplexField(ComplexFieldInterface $complexField, $fieldTypeIdentifier, $contentTypeIdentifier = null)
    {
        if ($contentTypeIdentifier == null) {
            $contentTypeIdentifier = '*';
        }
        $this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier] = $complexField;
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @return bool
     */
    public function managesField($fieldTypeIdentifier, $contentTypeIdentifier)
    {
        return (isset($this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier]) ||
            isset($this->fieldTypeMap['*'][$fieldTypeIdentifier]));
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @param mixed $fieldValue as gotten from a migration definition
     * @param array $context
     * @return mixed as usable in a Content create/update struct
     */
    public function getComplexFieldValue($fieldTypeIdentifier, $contentTypeIdentifier, $fieldValue, array $context = array())
    {
        if ($this->managesField($fieldTypeIdentifier, $contentTypeIdentifier)) {
            return $this->getFieldHandler($fieldTypeIdentifier, $contentTypeIdentifier)->createValue($fieldValue, $context);
        } else {
            $fieldType = $this->fieldTypeService->getFieldType($fieldTypeIdentifier);
            return $fieldType->fromHash($fieldValue);
            // was: error
            //throw new \InvalidArgumentException("Field of  can not be handled as it does not have a complex field class defined");
        }
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @return bool
     */
    public function managesFieldSettings($fieldTypeIdentifier, $contentTypeIdentifier)
    {
        if (!$this->managesField($fieldTypeIdentifier, $contentTypeIdentifier)) {
            return false;
        }

        $fieldHandler = $this->getFieldHandler($fieldTypeIdentifier, $contentTypeIdentifier);
        return ($fieldHandler instanceof FieldSettingsHandlerInterface);
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @param mixed $fieldSettings
     * @param array $context
     * @return mixed
     */
    public function fieldSettingsToHash($fieldTypeIdentifier, $contentTypeIdentifier, $fieldSettings, array $context = array())
    {
        if ($this->managesFieldSettings($fieldTypeIdentifier, $contentTypeIdentifier)) {
            return $this->getFieldHandler($fieldTypeIdentifier, $contentTypeIdentifier)->fieldSettingsToHash($fieldSettings, $context);
        } else {
            return $fieldSettings;
        }
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @param mixed $fieldSettingsHash
     * @param array $context
     * @return mixed
     */
    public function hashToFieldSettings($fieldTypeIdentifier, $contentTypeIdentifier, $fieldSettingsHash, array $context = array())
    {
        if ($this->managesFieldSettings($fieldTypeIdentifier, $contentTypeIdentifier)) {
            return $this->getFieldHandler($fieldTypeIdentifier, $contentTypeIdentifier)->hashTofieldSettings($fieldSettingsHash, $context);
        } else {
            return $fieldSettingsHash;
        }
    }

    protected function getFieldHandler($fieldTypeIdentifier, $contentTypeIdentifier) {
        if (isset($this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier])) {
            return $this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier];
        } else if (isset($this->fieldTypeMap['*'][$fieldTypeIdentifier])) {
            return $this->fieldTypeMap['*'][$fieldTypeIdentifier];
        }

        throw new \Exception("No complex field handler registered for field '$fieldTypeIdentifier' in content type '$contentTypeIdentifier'");
    }
}

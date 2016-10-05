<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;
use eZ\Publish\API\Repository\FieldTypeService;

class ComplexFieldManager
{
    /** @var ComplexFieldInterface[]  */
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
    public function addComplexField(ComplexFieldInterface $complexField, $fieldTypeIdentifier, $contentTypeIdentifier=null)
    {
        if ($contentTypeIdentifier == null) {
            $contentTypeIdentifier = '*';
        }
        $this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier] = $complexField;
    }

    /**
     * @param $fieldTypeIdentifier
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
     * @param mixed $fieldValue
     * @param array $context
     * @return mixed
     */
    public function getComplexFieldValue($fieldTypeIdentifier, $contentTypeIdentifier, $fieldValue, array $context = array())
    {
        if (isset($this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier]) || isset($this->fieldTypeMap['*'][$fieldTypeIdentifier]))
        {
            $fieldService = isset($this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier]) ?
                $this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier] :
                $this->fieldTypeMap['*'][$fieldTypeIdentifier];
            return $fieldService->createValue($fieldValue, $context);
        }
        else
        {
            $fieldType = $this->fieldTypeService->getFieldType($fieldTypeIdentifier);
            return $fieldType->fromHash($fieldValue);
            // was: error
            //throw new \InvalidArgumentException("Field of type '$fieldTypeIdentifier' can not be handled as it does not have a complex field class defined");
        }
    }
}

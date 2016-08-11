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
     * @param string $contentTypeIdentifier
     */
    public function addComplexField(ComplexFieldInterface $complexField, $contentTypeIdentifier)
    {
        $this->fieldTypeMap[$contentTypeIdentifier] = $complexField;
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param array $fieldValueArray
     * @param array $context
     * @return ComplexFieldInterface
     */
    public function getComplexFieldValue($fieldTypeIdentifier, array $fieldValueArray, array $context = array())
    {
        if (array_key_exists($fieldTypeIdentifier, $this->fieldTypeMap))
        {
            $fieldService = $this->fieldTypeMap[$fieldTypeIdentifier];
            return $fieldService->createValue($fieldValueArray, $context);
        }
        else
        {
            $fieldType = $this->fieldTypeService->getFieldType($fieldTypeIdentifier);
            return $fieldType->fromHash($fieldValueArray);
            // was: error
            //throw new \InvalidArgumentException("Field of type '$fieldTypeIdentifier' can not be handled as it does not have a complex field class defined");
        }
    }
}

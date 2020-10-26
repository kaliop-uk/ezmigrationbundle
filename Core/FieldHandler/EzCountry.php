<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\FieldTypeService;
use eZ\Publish\Core\FieldType\Country\Value as CountryValue;
use eZ\Publish\Core\FieldType\Selection\Value as SelectionValue;
use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;

class EzCountry extends AbstractFieldHandler implements FieldValueImporterInterface
{
    private $contentTypeService;

    private $fieldTypeService;

    public function __construct(ContentTypeService $contentTypeService, FieldTypeService $fieldTypeService)
    {
        $this->contentTypeService = $contentTypeService;
        $this->fieldTypeService = $fieldTypeService;
    }

    /**
     * Creates a value object to use as the field value when setting a country field type.
     *
     * @param $fieldValue string|string[] should work
     * @param array $context The context for execution of the current migrations. $hashValueContains f.e. the path to the migration
     * @return CountryValue
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        if ($fieldValue === null) {
            return new CountryValue();
        }

        if (is_string($fieldValue)) {
            $fieldValue = array($fieldValue);
        }

        $contentType = $this->contentTypeService->loadContentTypeByIdentifier($context['contentTypeIdentifier']);
        $field = $contentType->getFieldDefinition($context['fieldIdentifier']);
        $fieldType = $this->fieldTypeService->getFieldType($field->fieldTypeIdentifier);

        return $fieldType->fromHash($fieldValue);
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\FieldTypeService;
use eZ\Publish\Core\FieldType\Country\Value as CountryValue;
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

        $refsResolved = false;
        if (is_string($fieldValue)) {
            $fieldValue = $this->referenceResolver->resolveReference($fieldValue);
            $refsResolved = true;
        }
        if (!is_array($fieldValue)) {
            $fieldValue = array($fieldValue);
        }
        if (!$refsResolved) {
            foreach($fieldValue as $key => $countryValue) {
                $fieldValue[$key] = $this->referenceResolver->resolveReference($countryValue);
            }
        }

        $contentType = $this->contentTypeService->loadContentTypeByIdentifier($context['contentTypeIdentifier']);
        $field = $contentType->getFieldDefinition($context['fieldIdentifier']);
        $fieldType = $this->fieldTypeService->getFieldType($field->fieldTypeIdentifier);

        return $fieldType->fromHash($fieldValue);
    }

    // To avoid recursive expansion of references, we have to implement resolving logic only within this class
    public function doPreResolveStringReferences()
    {
        return false;
    }
}

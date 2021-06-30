<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use eZ\Publish\Core\FieldType\ImageAsset\Value;
use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;
use Kaliop\eZMigrationBundle\API\FieldDefinitionConverterInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;

class EzImageAsset extends AbstractFieldHandler implements FieldValueImporterInterface
{
    protected $contentMatcher;

    public function __construct(ContentMatcher $contentMatcher)
    {
        $this->contentMatcher = $contentMatcher;
    }

    /**
     * Creates a value object to use as the field value when setting an ez image asset field type.
     *
     * @param array|string|int $fieldValue The definition of the field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return Value
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        $altText = '';

        if ($fieldValue === null) {
            return new Value();
        }
        if (isset($fieldValue['alt_text'])) {
            $altText = $fieldValue['alt_text'];
        }

        if (is_array($fieldValue) && array_key_exists('destinationContentId', $fieldValue)) {
            // fromHash format
            $id = $fieldValue['destinationContentId'];
        } else {
            // simplified format
            $id = $fieldValue;
        }

        if ($id === null) {
            return new Value();
        }

        // 1. resolve relations
        $id = $this->referenceResolver->resolveReference($id);
        // 2. resolve remote ids
        $id = $this->contentMatcher->matchOneByKey($id)->id;

        return new Value($id, $altText);
    }
}
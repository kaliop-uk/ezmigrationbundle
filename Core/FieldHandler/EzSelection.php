<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use eZ\Publish\Core\FieldType\Selection\Value as SelectionValue;
use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;
use Kaliop\eZMigrationBundle\API\FieldDefinitionConverterInterface;
use \eZ\Publish\API\Repository\Repository;

class EzSelection extends AbstractFieldHandler implements FieldValueImporterInterface, FieldDefinitionConverterInterface
{
    protected $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Creates a value object to use as the field value when setting a selection field type.
     *
     * @param $fieldValue int|string|string[]|int[] should all work
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return SelectionValue
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        if ($fieldValue === null) {
            return new SelectionValue();
        }

        // allow user to pass in a single value
        if (is_string($fieldValue) || is_int($fieldValue)) {
            $fieldValue = array($fieldValue);
        }

        // allow user to pass in selection values by name
        $fieldSettings = null;
        foreach($fieldValue as $key => $val) {

            $val = $this->referenceResolver->resolveReference($val);

            if (is_string($val)) {
                if (ctype_digit($val)) {
                    $fieldValue[$key] = (int)$val;
                } else {
                    if ($fieldSettings === null) {
                        $fieldSettings = $this->loadFieldSettings($context['contentTypeIdentifier'], $context['fieldIdentifier']);
                    }
                    foreach($fieldSettings['options'] as $pos => $name) {
                        if ($name === $val) {
                            $fieldValue[$key] = $pos;
                            break;
                        }
                    }
                }
            }
        }

        return new SelectionValue($fieldValue);
    }

    /**
     * @param string $contentTypeIdentifier
     * @param string $fieldIdentifier
     * @return mixed
     */
    protected function loadFieldSettings($contentTypeIdentifier, $fieldIdentifier)
    {
        $contentTypeService = $this->repository->getContentTypeService();
        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);
        $fieldDefinition = $contentType->getFieldDefinition($fieldIdentifier);
        return $fieldDefinition->fieldSettings;
    }

    public function fieldSettingsToHash($settingsValue, array $context = array())
    {
        return $settingsValue;
    }

    public function hashToFieldSettings($settingsHash, array $context = array())
    {
        foreach ($settingsHash['options'] as $key => $value) {
            if (!is_int($key) && !ctype_digit($key)) {
                throw new \Exception("The list of values allowed for an eZSelection field can only use integer keys, found: '$key'");
            }
        }
        return $settingsHash;
    }
}

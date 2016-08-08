<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\Core\Repository\Values\ContentType\FieldDefinition;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use Kaliop\eZMigrationBundle\Core\ReferenceResolver\LocationResolver;

/**
 * Methods to handle content type migrations
 */
class ContentTypeManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('content_type');

    protected $locationReferenceResolver;

    public function __construct(LocationResolver $locationReferenceResolver)
    {
        $this->locationReferenceResolver = $locationReferenceResolver;
    }

    /**
     * Method to handle the create operation of the migration instructions
     */
    protected function create()
    {
        // Authenticate the user
        $this->loginUser();

        foreach(array('identifier', 'content_type_group', 'name_pattern', 'name', 'attributes') as $key) {
            if (!array_key_exists($key, $this->dsl)) {
                throw new \Exception("The '$key' key is missing in a content type creation definition");
            }
        }

        $contentTypeService = $this->repository->getContentTypeService();
        $contentTypeGroup = $contentTypeService->loadContentTypeGroup($this->dsl['content_type_group']);

        $contentTypeCreateStruct = $contentTypeService->newContentTypeCreateStruct($this->dsl['identifier']);
        $contentTypeCreateStruct->mainLanguageCode = self::DEFAULT_LANGUAGE_CODE;

        // Object Name pattern
        $contentTypeCreateStruct->nameSchema = $this->dsl['name_pattern'];

        // set names for the content type
        $contentTypeCreateStruct->names = array(
            self::DEFAULT_LANGUAGE_CODE => $this->dsl['name'],
        );

        if (array_key_exists('description', $this->dsl)) {
            // set description for the content type
            $contentTypeCreateStruct->descriptions = array(
                self::DEFAULT_LANGUAGE_CODE => $this->dsl['description'],
            );
        }

        if (array_key_exists('url_name_pattern', $this->dsl)) {
            $contentTypeCreateStruct->urlAliasSchema = $this->dsl['url_name_pattern'];
        }

        if (array_key_exists('is_container', $this->dsl)) {
            $contentTypeCreateStruct->isContainer = $this->dsl['is_container'];
        }

        // Add attributes
        foreach ($this->dsl['attributes'] as $position => $attribute) {
            $fieldDefinition = $this->createFieldDefinition($contentTypeService, $attribute);
            $fieldDefinition->position = ++$position;
            $contentTypeCreateStruct->addFieldDefinition($fieldDefinition);
        }

        // Publish new class
        $contentTypeDraft = $contentTypeService->createContentType($contentTypeCreateStruct, array($contentTypeGroup));
        $contentTypeService->publishContentTypeDraft($contentTypeDraft);

        // Set references
        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeDraft->identifier);
        $this->setReferences($contentType);
    }

    /**
     * Method to handle the update operation of the migration instructions
     */
    protected function update()
    {
        // Authenticate the user
        $this->loginUser();

        $contentTypeService = $this->repository->getContentTypeService();

        if (!array_key_exists('identifier', $this->dsl)) {
            throw new \Exception("The identifier of a content type is required in order to update it.");
        }

        $contentTypeIdentifier = $this->dsl['identifier'];
        if ($this->referenceResolver->isReference($contentTypeIdentifier)) {
            $contentTypeIdentifier = $this->referenceResolver->getReferenceValue($contentTypeIdentifier);
        }
        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);

        $contentTypeDraft = $contentTypeService->createContentTypeDraft($contentType);

        $contentTypeUpdateStruct = $contentTypeService->newContentTypeUpdateStruct();
        $contentTypeUpdateStruct->mainLanguageCode = self::DEFAULT_LANGUAGE_CODE;

        if (array_key_exists('new_identifier', $this->dsl)) {
            $contentTypeUpdateStruct->identifier = $this->dsl['new_identifier'];
        }

        if (array_key_exists('name', $this->dsl)) {
            $contentTypeUpdateStruct->names = array(self::DEFAULT_LANGUAGE_CODE => $this->dsl['name']);
        }

        if (array_key_exists('description', $this->dsl)) {
            $contentTypeUpdateStruct->descriptions = array(
                self::DEFAULT_LANGUAGE_CODE => $this->dsl['description'],
            );
        }

        if (array_key_exists('name_pattern', $this->dsl)) {
            $contentTypeUpdateStruct->nameSchema = $this->dsl['name_pattern'];
        }

        if (array_key_exists('url_name_pattern', $this->dsl)) {
            $contentTypeUpdateStruct->urlAliasSchema = $this->dsl['url_name_pattern'];
        }

        if (array_key_exists('is_container', $this->dsl)) {
            $contentTypeUpdateStruct->isContainer = $this->dsl['is_container'];
        }

        // Add/edit attributes
        if (array_key_exists('attributes', $this->dsl)) {
            foreach ($this->dsl['attributes'] as $key => $attribute) {

                if (!array_key_exists('identifier', $attribute)) {
                    throw new \Exception("The 'identifier' of an attribute is missing in the content type update definition.");
                }

                $existingFieldDefinition = $this->contentTypeHasFieldDefinition($contentType, $attribute['identifier']);
                if ($existingFieldDefinition) {
                    // Edit existing attribute
                    $fieldDefinitionUpdateStruct = $this->updateFieldDefinition($contentTypeService, $attribute);
//                    $fieldDefinitionUpdateStruct = $this->updateFieldSettingsFromExisting($fieldDefinitionUpdateStruct, $existingFieldDefinition);
                    $contentTypeService->updateFieldDefinition(
                        $contentTypeDraft,
                        $existingFieldDefinition,
                        $fieldDefinitionUpdateStruct
                    );
                } else {
                    // Add new attribute
                    $newFieldDefinition = $this->createFieldDefinition($contentTypeService, $attribute);
                    // New attributes are positioned at the end of the list
                    $newFieldDefinition->position = count($contentType->fieldDefinitions);
                    $contentTypeService->addFieldDefinition($contentTypeDraft, $newFieldDefinition);
                }
            }
        }

        // Remove attributes
        if (array_key_exists('remove_attributes', $this->dsl)) {
            foreach ($this->dsl['remove_attributes'] as $attribute) {
                $existingFieldDefinition = $this->contentTypeHasFieldDefinition($contentType, $attribute);
                if ($existingFieldDefinition) {
                    $contentTypeService->removeFieldDefinition($contentTypeDraft, $existingFieldDefinition);
                }
            }
        }

        $contentTypeService->updateContentTypeDraft($contentTypeDraft, $contentTypeUpdateStruct);
        $contentTypeService->publishContentTypeDraft($contentTypeDraft);

        // Set references
        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeDraft->identifier);
        $this->setReferences($contentType);
    }

    /**
     * Method to handle the delete operation of the migration instructions
     */
    protected function delete()
    {
        $this->loginUser();

        $contentTypeService = $this->repository->getContentTypeService();

        if (!array_key_exists('identifier', $this->dsl)) {
            throw new \Exception("The identifier of a content type is required in order to delete it.");
        }

        $contentTypeIdentifier = $this->dsl['identifier'];
        if ($this->referenceResolver->isReference($contentTypeIdentifier)) {
            $contentTypeIdentifier = $this->referenceResolver->getReferenceValue($contentTypeIdentifier);
        }
        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);

        $contentTypeService->deleteContentType($contentType);
    }

    /**
     * Sets references to object attributes
     *
     * The Content Type Manager currently supports setting references to the content type id and identifier
     *
     * @throws \InvalidArgumentException When trying to set
     *
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentType $contentType
     * @return boolean
     */
    protected function setReferences($contentType)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        foreach ($this->dsl['references'] as $reference) {
            switch ($reference['attribute']) {
                case 'content_type_id':
                case 'id':
                    $value = $contentType->id;
                    break;
                case 'content_type_identifier':
                case 'identifier':
                    $value = $contentType->identifier;
                    break;
                default:
                    throw new \InvalidArgumentException('Content Type Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }
    }

    /**
     * Helper function to create field definitions based to be added to a new/existing content type.
     *
     * @todo Add translation support if needed
     * @param ContentTypeService $contentTypeService
     * @param array $attribute
     * @return \eZ\Publish\API\Repository\Values\ContentType\FieldDefinitionCreateStruct
     */
    private function createFieldDefinition(ContentTypeService $contentTypeService, array $attribute)
    {
        $fieldDefinition = $contentTypeService->newFieldDefinitionCreateStruct(
            $attribute['identifier'],
            $attribute['type']
        );

        foreach ($attribute as $key => $value) {
            if (!in_array($key, array('identifier', 'type'))) {
                switch ($key) {
                    case 'name':
                        $fieldDefinition->names = array(self::DEFAULT_LANGUAGE_CODE => $value);
                        break;
                    case 'description':
                        $fieldDefinition->descriptions = array(self::DEFAULT_LANGUAGE_CODE => $value);
                        break;
                    case 'required':
                        $fieldDefinition->isRequired = $value;
                        break;
                    case 'searchable':
                        $fieldDefinition->isSearchable = $value;
                        break;
                    case 'info-collector':
                        $fieldDefinition->isInfoCollector = $value;
                        break;
                    case 'disable-translation':
                        $fieldDefinition->isTranslatable = !$value;
                        break;
                    case 'category':
                        $fieldDefinition->fieldGroup = $value == 'default' ? 'content' : $value;
                        break;
                    case 'default-value':
                        $fieldDefinition->defaultValue = $value;
                        break;
                    case 'field-settings':
                        $fieldDefinition->fieldSettings = $this->setFieldSettings($value);
                        break;
                    case 'validator-configuration':
                        $fieldDefinition->validatorConfiguration = $value;
                        break;
                }
            }
        }

        return $fieldDefinition;
    }

    /**
     * Helper function to update field definitions based to be added to a new/existing content type.
     *
     * @todo Add translation support if needed
     * @param ContentTypeService $contentTypeService
     * @param array $attribute
     * @return \eZ\Publish\API\Repository\Values\ContentType\FieldDefinitionUpdateStruct
     */
    private function updateFieldDefinition(ContentTypeService $contentTypeService, array $attribute)
    {
        $fieldDefinitionUpdateStruct = $contentTypeService->newFieldDefinitionUpdateStruct();

        foreach ($attribute as $key => $value) {
            if (!in_array($key, array('identifier', 'type'))) {
                switch ($key) {
                    case 'new_identifier':
                        $fieldDefinitionUpdateStruct->identifier = $value;
                        break;
                    case 'name':
                        $fieldDefinitionUpdateStruct->names = array(self::DEFAULT_LANGUAGE_CODE => $value);
                        break;
                    case 'description':
                        $fieldDefinitionUpdateStruct->descriptions = array(self::DEFAULT_LANGUAGE_CODE => $value);
                        break;
                    case 'required':
                        $fieldDefinitionUpdateStruct->isRequired = $value;
                        break;
                    case 'searchable':
                        $fieldDefinitionUpdateStruct->isSearchable = $value;
                        break;
                    case 'info-collector':
                        $fieldDefinitionUpdateStruct->isInfoCollector = $value;
                        break;
                    case 'disable-translation':
                        $fieldDefinitionUpdateStruct->isTranslatable = !$value;
                        break;
                    case 'category':
                        $fieldDefinitionUpdateStruct->fieldGroup = $value == 'default' ? 'content' : $value;
                        break;
                    case 'default-value':
                        $fieldDefinitionUpdateStruct->defaultValue = $value;
                        break;
                    case 'field-settings':
                        $fieldDefinitionUpdateStruct->fieldSettings = $this->setFieldSettings($value);
                        break;
                    case 'position':
                        $fieldDefinitionUpdateStruct->position = $value;
                        break;
                    case 'validator-configuration':
                        $fieldDefinitionUpdateStruct->validatorConfiguration = $value;
                        break;
                }
            }
        }

        return $fieldDefinitionUpdateStruct;
    }

    /*private function updateFieldSettingsFromExisting($fieldDefinitionUpdateStruct, FieldDefinition $existingFieldDefinition) {
        if (is_null($fieldDefinitionUpdateStruct->fieldSettings)) {
            $fieldDefinitionUpdateStruct->fieldSettings = $existingFieldDefinition->getFieldSettings();
        }

        if (is_null($fieldDefinitionUpdateStruct->identifier)) {
            $fieldDefinitionUpdateStruct->identifier = $existingFieldDefinition->identifier;
        }

        if (is_null($fieldDefinitionUpdateStruct->names)) {
            $fieldDefinitionUpdateStruct->names = $existingFieldDefinition->names;
        }

        if (is_null($fieldDefinitionUpdateStruct->descriptions)) {
            $fieldDefinitionUpdateStruct->descriptions = $existingFieldDefinition->descriptions;
        }

        if (is_null($fieldDefinitionUpdateStruct->fieldGroup)) {
            $fieldDefinitionUpdateStruct->fieldGroup = $existingFieldDefinition->fieldGroup;
        }

        if (is_null($fieldDefinitionUpdateStruct->position)) {
            $fieldDefinitionUpdateStruct->position = $existingFieldDefinition->position;
        }

        if (is_null($fieldDefinitionUpdateStruct->isTranslatable)) {
            $fieldDefinitionUpdateStruct->isTranslatable = $existingFieldDefinition->isTranslatable;
        }

        if (is_null($fieldDefinitionUpdateStruct->isRequired)) {
            $fieldDefinitionUpdateStruct->isRequired = $existingFieldDefinition->isRequired;
        }

        if (is_null($fieldDefinitionUpdateStruct->isInfoCollector)) {
            $fieldDefinitionUpdateStruct->isInfoCollector = $existingFieldDefinition->isInfoCollector;
        }

        if (is_null($fieldDefinitionUpdateStruct->validatorConfiguration)) {
            $fieldDefinitionUpdateStruct->validatorConfiguration = $existingFieldDefinition->validatorConfiguration;
        }

        if (is_null($fieldDefinitionUpdateStruct->fieldSettings)) {
            $fieldDefinitionUpdateStruct->fieldSettings = $existingFieldDefinition->getFieldSettings();
        }

        if (is_null($fieldDefinitionUpdateStruct->defaultValue)) {
            $fieldDefinitionUpdateStruct->defaultValue = $existingFieldDefinition->defaultValue;
        }

        if (is_null($fieldDefinitionUpdateStruct->isSearchable)) {
            $fieldDefinitionUpdateStruct->isSearchable = $existingFieldDefinition->isSearchable;
        }

        return $fieldDefinitionUpdateStruct;
    }*/

    private function setFieldSettings($value)
    {
        // Updating any references in the value array

        if (is_array($value)) {
            $ret = array();
            foreach ($value as $key => $val)
            {
                $ret[$key] = $val;

                // we do NOT check for refs in field settings which are arrays, even though we could, maybe *should*...
                if (!is_array($val)) {
                    if ($this->referenceResolver->isReference($val)) {
                        $ret[$key] = $this->referenceResolver->getReferenceValue($val);
                    }
                }
            }
        }
        else {
            $ret = $value;

            if ($this->referenceResolver->isReference($value)) {
                $ret = $this->referenceResolver->getReferenceValue($value);
            }
        }

        return $ret;
    }

    /**
     * Helper to find out if a Field is already defined in a ContentType
     *
     * @param ContentType $contentType
     * @param string $fieldIdentifier
     * @return null|FieldDefinition
     */
    private function contentTypeHasFieldDefinition(ContentType $contentType, $fieldIdentifier)
    {
        $existingFieldDefinitions = $contentType->fieldDefinitions;

        foreach ($existingFieldDefinitions as $existingFieldDefinition) {
            if ($fieldIdentifier == $existingFieldDefinition->identifier) {
                return $existingFieldDefinition;
            }
        }

        return 0;
    }
}

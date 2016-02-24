<?php

namespace Kaliop\eZMigrationBundle\Core\API\Managers;

use eZ\Publish\API\Repository\ContentTypeService;
use Kaliop\eZMigrationBundle\Core\API\ReferenceHandler;

/**
 * Class ContentTypeManager
 *
 * Methods to handle content type migrations
 *
 * @package Kaliop\eZMigrationBundle\Core\API\Managers
 */
class ContentTypeManager extends AbstractManager
{

    /**
     * Method to handle the create operation of the migration instructions
     */
    public function create()
    {
        // Authenticate the user
        $this->loginUser();

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
    public function update()
    {
        // Authenticate the user
        $this->loginUser();

        $contentTypeService = $this->repository->getContentTypeService();

        $contentType = $contentTypeService->loadContentTypeByIdentifier($this->dsl['identifier']);
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
            foreach ($this->dsl['attributes'] as $attribute) {
                $existingFieldDefinition = $this->contentTypeHasFieldDefinition($contentType, $attribute['identifier']);
                if ($existingFieldDefinition) {
                    // Edit exisiting attribute
                    $fieldDefinitionUpdateStruct = $this->updateFieldDefinition($contentTypeService, $attribute);
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
    public function delete()
    {
        if (array_key_exists('identifier', $this->dsl)) {
            // Authenticate the user
            $this->loginUser();

            $contentTypeService = $this->repository->getContentTypeService();
            $contentType = $contentTypeService->loadContentTypeByIdentifier($this->dsl['identifier']);
            $contentTypeService->deleteContentType($contentType);
        }
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

        $referenceHandler = ReferenceHandler::instance();

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

            $referenceHandler->addReference($reference['identifier'], $value);
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

    private function setFieldSettings( $value )
    {
        // Updating any references in the value array
        $ret = $value;
        if (is_array($value))
        {
            $ret = array();
            foreach ($value as $key => $val)
            {
                $ret[$key] = $val;
                if ( $this->isReference($val) )
                {
                    $ret[$key] = $this->getReference($val);
                }
            }
        }
        else if ( $this->isReference($value) )
        {
            $ret = $this->getReference($value);
        }

        return $ret;
    }

    /**
     * Helper to find out if a Field is already defined in a ContentType
     *
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentType $contentType
     * @param $fieldIdentifier
     * @return int
     */
    private function contentTypeHasFieldDefinition($contentType, $fieldIdentifier)
    {
        $existingFieldDefinitions = $contentType->fieldDefinitions;

        foreach ($existingFieldDefinitions as $existingFieldDefinition) {
            if (strcmp($fieldIdentifier, $existingFieldDefinition->identifier) == 0) {
                return $existingFieldDefinition;
            }
        }

        return 0;
    }
}

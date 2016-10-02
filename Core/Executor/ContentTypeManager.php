<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\Core\Repository\Values\ContentType\FieldDefinition;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use Kaliop\eZMigrationBundle\API\Collection\ContentTypeCollection;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentTypeMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentTypeGroupMatcher;

/**
 * Methods to handle content type migrations
 */
class ContentTypeManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('content_type');

    protected $contentTypeMatcher;
    protected $contentTypeGroupMatcher;

    public function __construct(ContentTypeMatcher $matcher, ContentTypeGroupMatcher $contentTypeGroupMatcher)
    {
        $this->contentTypeMatcher = $matcher;
        $this->contentTypeGroupMatcher = $contentTypeGroupMatcher;
    }

    /**
     * Method to handle the create operation of the migration instructions
     */
    protected function create()
    {
        foreach(array('identifier', 'content_type_group', 'name_pattern', 'name', 'attributes') as $key) {
            if (!isset($this->dsl[$key])) {
                throw new \Exception("The '$key' key is missing in a content type creation definition");
            }
        }

        $contentTypeService = $this->repository->getContentTypeService();
        $contentTypeGroup = $this->contentTypeGroupMatcher->matchByKey($this->dsl['content_type_group']);

        $contentTypeCreateStruct = $contentTypeService->newContentTypeCreateStruct($this->dsl['identifier']);
        $contentTypeCreateStruct->mainLanguageCode = $this->getLanguageCode();

        // Object Name pattern
        $contentTypeCreateStruct->nameSchema = $this->dsl['name_pattern'];

        // set names for the content type
        $contentTypeCreateStruct->names = array(
            $this->getLanguageCode() => $this->dsl['name'],
        );

        if (isset($this->dsl['description'])) {
            // set description for the content type
            $contentTypeCreateStruct->descriptions = array(
                $this->getLanguageCode() => $this->dsl['description'],
            );
        }

        if (isset($this->dsl['url_name_pattern'])) {
            $contentTypeCreateStruct->urlAliasSchema = $this->dsl['url_name_pattern'];
        }

        if (isset($this->dsl['is_container'])) {
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

        return $contentType;
    }

    /**
     * Method to handle the update operation of the migration instructions
     */
    protected function update()
    {
        $contentTypeCollection = $this->matchContentTypes('update');

        if (count($contentTypeCollection) > 1 && array_key_exists('references', $this->dsl)) {
            throw new \Exception("Can not execute Content Type update because multiple types match, and a references section is specified in the dsl. References can be set when only 1 type matches");
        }

        if (count($contentTypeCollection) > 1 && array_key_exists('new_identifier', $this->dsl)) {
            throw new \Exception("Can not execute Content Type update because multiple roles match, and a new_identifier is specified in the dsl.");
        }

        $contentTypeService = $this->repository->getContentTypeService();
        foreach ($contentTypeCollection as $key => $contentType) {

            $contentTypeDraft = $contentTypeService->createContentTypeDraft($contentType);

            $contentTypeUpdateStruct = $contentTypeService->newContentTypeUpdateStruct();
            $contentTypeUpdateStruct->mainLanguageCode = $this->getLanguageCode();

            if (isset($this->dsl['new_identifier'])) {
                $contentTypeUpdateStruct->identifier = $this->dsl['new_identifier'];
            }

            if (isset($this->dsl['name'])) {
                $contentTypeUpdateStruct->names = array($this->getLanguageCode() => $this->dsl['name']);
            }

            if (isset($this->dsl['description'])) {
                $contentTypeUpdateStruct->descriptions = array(
                    $this->getLanguageCode() => $this->dsl['description'],
                );
            }

            if (isset($this->dsl['name_pattern'])) {
                $contentTypeUpdateStruct->nameSchema = $this->dsl['name_pattern'];
            }

            if (isset($this->dsl['url_name_pattern'])) {
                $contentTypeUpdateStruct->urlAliasSchema = $this->dsl['url_name_pattern'];
            }

            if (isset($this->dsl['is_container'])) {
                $contentTypeUpdateStruct->isContainer = $this->dsl['is_container'];
            }

            // Add/edit attributes
            if (isset($this->dsl['attributes'])) {
                foreach ($this->dsl['attributes'] as $attribute) {
                    $existingFieldDefinition = $this->contentTypeHasFieldDefinition($contentType, $attribute['identifier']);
                    if ($existingFieldDefinition) {
                        // Edit existing attribute
                        $fieldDefinitionUpdateStruct = $this->updateFieldDefinition($contentTypeService, $attribute);
                        //$fieldDefinitionUpdateStruct = $this->updateFieldSettingsFromExisting($fieldDefinitionUpdateStruct, $existingFieldDefinition);
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
            if (isset($this->dsl['remove_attributes'])) {
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
            if (isset($this->dsl['new_identifier'])) {
                $contentType = $contentTypeService->loadContentTypeByIdentifier($this->dsl['new_identifier']);
            } else {
                $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeDraft->identifier);
            }

            $contentTypeCollection[$key] = $contentType;
        }

        $this->setReferences($contentTypeCollection);

        return $contentTypeCollection;
    }

    /**
     * Method to handle the delete operation of the migration instructions
     */
    protected function delete()
    {
        $contentTypeCollection = $this->matchContentTypes('delete');

        $contentTypeService = $this->repository->getContentTypeService();

        foreach ($contentTypeCollection as $contentType) {
            $contentTypeService->deleteContentType($contentType);
        }

        return $contentTypeCollection;
    }

    /**
     * @param string $action
     * @return RoleCollection
     * @throws \Exception
     */
    protected function matchContentTypes($action)
    {
        if (!isset($this->dsl['identifier']) && !isset($this->dsl['match'])) {
            throw new \Exception("The identifier of a contenttype or a match condition is required to $action it.");
        }

        // Backwards compat
        if (!isset($this->dsl['match'])) {
            $this->dsl['match'] = array('identifier' => $this->dsl['identifier']);
        }

        $match = $this->dsl['match'];

        // convert the references passed in the match
        foreach ($match as $condition => $values) {
            if (is_array($values)) {
                foreach ($values as $position => $value) {
                $match[$condition][$position] = $this->resolveReferences($value);
                }
            } else {
                $match[$condition] = $this->resolveReferences($values);
            }
        }

        return $this->contentTypeMatcher->match($match);
    }

    /**
     * Sets references to object attributes
     *
     * The Content Type Manager currently supports setting references to the content type id and identifier
     *
     * @throws \InvalidArgumentException When trying to set
     *
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentType|ContentTypeCollection $contentType
     * @return boolean
     */
    protected function setReferences($contentType)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        if ($contentType instanceof ContentTypeCollection) {
            if (count($contentType) > 1) {
                throw new \InvalidArgumentException('ContentType Manager does not support setting references for creating/updating of multiple content types');
            }
            $contentType = reset($contentType);
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
     * @throws \Exception
     */
    private function createFieldDefinition(ContentTypeService $contentTypeService, array $attribute)
    {
        if (!isset($attribute['identifier']) || !isset($attribute['type'])) {
            throw new \Exception("Keys 'type' and 'identifier' are mandatory to define a new field in a field type");
        }

        $fieldDefinition = $contentTypeService->newFieldDefinitionCreateStruct(
            $attribute['identifier'],
            $attribute['type']
        );

        foreach ($attribute as $key => $value) {
            switch ($key) {
                case 'name':
                    $fieldDefinition->names = array($this->getLanguageCode() => $value);
                    break;
                case 'description':
                    $fieldDefinition->descriptions = array($this->getLanguageCode() => $value);
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

        return $fieldDefinition;
    }

    /**
     * Helper function to update field definitions based to be added to a new/existing content type.
     *
     * @todo Add translation support if needed
     * @param ContentTypeService $contentTypeService
     * @param array $attribute
     * @return \eZ\Publish\API\Repository\Values\ContentType\FieldDefinitionUpdateStruct
     * @throws \Exception
     */
    private function updateFieldDefinition(ContentTypeService $contentTypeService, array $attribute)
    {
        if (!isset($attribute['identifier'])) {
            throw new \Exception("The 'identifier' of an attribute is missing in the content type update definition.");
        }

        $fieldDefinitionUpdateStruct = $contentTypeService->newFieldDefinitionUpdateStruct();

        foreach ($attribute as $key => $value) {
            switch ($key) {
                case 'new_identifier':
                    $fieldDefinitionUpdateStruct->identifier = $value;
                    break;
                case 'name':
                    $fieldDefinitionUpdateStruct->names = array($this->getLanguageCode() => $value);
                    break;
                case 'description':
                    $fieldDefinitionUpdateStruct->descriptions = array($this->getLanguageCode() => $value);
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

        return $fieldDefinitionUpdateStruct;
    }

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
                    $ret[$key] = $this->resolveReferences($val);
                }
            }
        } else {
            $ret = $this->resolveReferences($value);
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

        return null;
    }
}

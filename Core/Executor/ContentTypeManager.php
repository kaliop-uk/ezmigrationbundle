<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use Kaliop\eZMigrationBundle\API\Collection\ContentTypeCollection;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentTypeMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentTypeGroupMatcher;
use Kaliop\eZMigrationBundle\Core\ComplexField\ComplexFieldManager;

/**
 * Handles content type migrations
 */
class ContentTypeManager extends RepositoryExecutor implements MigrationGeneratorInterface
{
    protected $supportedStepTypes = array('content_type');

    protected $contentTypeMatcher;
    protected $contentTypeGroupMatcher;
    // This resolver is used to resolve references in content-type settings definitions
    protected $extendedReferenceResolver;
    protected $complexFieldManager;

    public function __construct(ContentTypeMatcher $matcher, ContentTypeGroupMatcher $contentTypeGroupMatcher,
        ReferenceResolverInterface $extendedReferenceResolver, ComplexFieldManager $complexFieldManager)
    {
        $this->contentTypeMatcher = $matcher;
        $this->contentTypeGroupMatcher = $contentTypeGroupMatcher;
        $this->extendedReferenceResolver = $extendedReferenceResolver;
        $this->complexFieldManager = $complexFieldManager;
    }

    /**
     * Method to handle the create operation of the migration instructions
     */
    protected function create()
    {
        foreach (array('identifier', 'content_type_group', 'name_pattern', 'name', 'attributes') as $key) {
            if (!isset($this->dsl[$key])) {
                throw new \Exception("The '$key' key is missing in a content type creation definition");
            }
        }

        $contentTypeService = $this->repository->getContentTypeService();

        $contentTypeGroupId = $this->dsl['content_type_group'];
        $contentTypeGroupId = $this->referenceResolver->resolveReference($contentTypeGroupId);
        $contentTypeGroup = $this->contentTypeGroupMatcher->matchOneByKey($contentTypeGroupId);

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

        if (isset($this->dsl['default_always_available'])) {
            $contentTypeCreateStruct->defaultAlwaysAvailable = $this->dsl['default_always_available'];
        }

        // Add attributes
        // NB: seems like eZ gets mixed up if we pass some attributes with a position and some without...
        // We go out of our way to avoid collisions and preserve an order: fields without position go *last*
        $maxFieldDefinitionPos = 0;
        $fieldDefinitions = array();
        foreach ($this->dsl['attributes'] as $position => $attribute) {
            $fieldDefinition = $this->createFieldDefinition($contentTypeService, $attribute, $this->dsl['identifier']);
            $maxFieldDefinitionPos = $fieldDefinition->position > $maxFieldDefinitionPos ? $fieldDefinition->position : $maxFieldDefinitionPos;
            $fieldDefinitions[] = $fieldDefinition;
        }
        foreach ($fieldDefinitions as $fieldDefinition) {
            if ($fieldDefinition->position == 0) {
                $fieldDefinition->position = ++$maxFieldDefinitionPos;
            }
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
                // NB: seems like eZ gets mixed up if we pass some attributes with a position and some without...
                // We go out of our way to avoid collisions and preserve order
                $maxFieldDefinitionPos = count($contentType->fieldDefinitions);
                $newFieldDefinitions = array();
                foreach ($this->dsl['attributes'] as $attribute) {

                    $existingFieldDefinition = $this->contentTypeHasFieldDefinition($contentType, $attribute['identifier']);

                    if ($existingFieldDefinition) {
                        // Edit existing attribute
                        $fieldDefinitionUpdateStruct = $this->updateFieldDefinition(
                            $contentTypeService, $attribute, $attribute['identifier'], $contentType->identifier
                        );
                        $contentTypeService->updateFieldDefinition(
                            $contentTypeDraft,
                            $existingFieldDefinition,
                            $fieldDefinitionUpdateStruct
                        );
                        if ($fieldDefinitionUpdateStruct->position > 0) {
                            $maxFieldDefinitionPos = $fieldDefinitionUpdateStruct->position > $maxFieldDefinitionPos ? $fieldDefinitionUpdateStruct->position : $maxFieldDefinitionPos;
                        } else {
                            $maxFieldDefinitionPos = $existingFieldDefinition->position > $maxFieldDefinitionPos ? $existingFieldDefinition->position : $maxFieldDefinitionPos;
                        }

                    } else {
                        // Create new attributes, keep them in temp array
                        $newFieldDefinition = $this->createFieldDefinition($contentTypeService, $attribute, $contentType->identifier);
                        $maxFieldDefinitionPos = $newFieldDefinition->position > $maxFieldDefinitionPos ? $newFieldDefinition->position : $maxFieldDefinitionPos;
                        $newFieldDefinitions[] = $newFieldDefinition;
                    }
                }

                // Add new attributes
                foreach($newFieldDefinitions as $newFieldDefinition) {
                    if ($newFieldDefinition->position == 0) {
                        $newFieldDefinition->position = ++$maxFieldDefinitionPos;
                    }
                    $contentTypeService->addFieldDefinition($contentTypeDraft, $newFieldDefinition);
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
     * @return ContentTypeCollection
     * @throws \Exception
     */
    protected function matchContentTypes($action)
    {
        if (!isset($this->dsl['identifier']) && !isset($this->dsl['match'])) {
            throw new \Exception("The identifier of a content type or a match condition is required to $action it");
        }

        // Backwards compat
        if (!isset($this->dsl['match'])) {
            $this->dsl['match'] = array('identifier' => $this->dsl['identifier']);
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($this->dsl['match']);

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
     * @return bool
     */
    protected function setReferences($contentType)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        if ($contentType instanceof ContentTypeCollection) {
            if (count($contentType) > 1) {
                throw new \InvalidArgumentException('Content Type Manager does not support setting references for creating/updating of multiple content types');
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
                case 'creation_date':
                    $value = $contentType->creationDate->getTimestamp();
                    break;
                case 'modification_date':
                    $value = $contentType->modificationDate->getTimestamp();
                    break;
                case 'name_pattern':
                    $value = $contentType->nameSchema;
                    break;
                case 'remote_id':
                    $value = $contentType->remoteId;
                    break;
                case 'status':
                    $value = $contentType->status;
                    break;
                case 'url_name_pattern':
                    $value = $contentType->urlAliasSchema;
                    break;
                default:
                    throw new \InvalidArgumentException('Content Type Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }


    /**
     * @param array $matchCondition
     * @param string $mode
     * @throws \Exception
     * @return array
     */
    public function generateMigration(array $matchCondition, $mode)
    {
        $previousUserId = $this->loginUser(self::ADMIN_USER_ID);
        $contentTypeCollection = $this->contentTypeMatcher->match($matchCondition);
        $data = array();

        /** @var \eZ\Publish\API\Repository\Values\ContentType\ContentType $contentType */
        foreach ($contentTypeCollection as $contentType) {

            $contentTypeData = array(
                'type' => reset($this->supportedStepTypes),
                'mode' => $mode
            );

            switch ($mode) {
                case 'create':
                    $contentTypeGroups = $contentType->getContentTypeGroups();
                    $contentTypeData = array_merge(
                        $contentTypeData,
                        array(
                            'content_type_group' => reset($contentTypeGroups)->identifier,
                            'identifier' => $contentType->identifier
                        )
                    );
                    break;
                case 'update':
                    $contentTypeData = array_merge(
                        $contentTypeData,
                        // q: are we allowed to change the group in updates ?
                        array(
                            'match' => array(
                                ContentTypeMatcher::MATCH_CONTENTTYPE_IDENTIFIER => $contentType->identifier
                            ),
                            'new_identifier' => $contentType->identifier,
                        )
                    );
                    break;
                case 'delete':
                    $contentTypeData = array_merge(
                        $contentTypeData,
                        array(
                            'match' => array(
                                ContentTypeMatcher::MATCH_CONTENTTYPE_IDENTIFIER => $contentType->identifier
                            )
                        )
                    );
                    break;
                default:
                    throw new \Exception("Executor 'content_type' doesn't support mode '$mode'");
            }

            if ($mode != 'delete') {

                $fieldTypeService = $this->repository->getFieldTypeService();

                $attributes = array();
                foreach ($contentType->getFieldDefinitions() as $i => $fieldDefinition) {
                    $fieldTypeIdentifier = $fieldDefinition->fieldTypeIdentifier;
                    $attribute = array(
                        'identifier' => $fieldDefinition->identifier,
                        'type' => $fieldTypeIdentifier,
                        'name' => $fieldDefinition->getName($this->getLanguageCode()),
                        'description' => (string)$fieldDefinition->getDescription($this->getLanguageCode()),
                        'required' => $fieldDefinition->isRequired,
                        'searchable' => $fieldDefinition->isSearchable,
                        'info-collector' => $fieldDefinition->isInfoCollector,
                        'disable-translation' => !$fieldDefinition->isTranslatable,
                        'category' => $fieldDefinition->fieldGroup,
                        // Should we cheat and do like the eZ4 Admin Interface and used sequential numbering 1,2,3... ?
                        // But what if the end user then edits the 'update' migration and only leaves in it a single
                        // field position update? He/she might be surprised when executing it...
                        'position' => $fieldDefinition->position
                    );

                    $fieldType = $fieldTypeService->getFieldType($fieldTypeIdentifier);
                    $nullValue = $fieldType->getEmptyValue();
                    if ($fieldDefinition->defaultValue != $nullValue) {
                        $attribute['default-value'] = $this->complexFieldManager->fieldValueToHash(
                            $fieldTypeIdentifier, $contentType->identifier, $fieldDefinition->defaultValue
                        );
                    }

                    $attribute['field-settings'] = $this->complexFieldManager->fieldSettingsToHash(
                        $fieldTypeIdentifier, $contentType->identifier, $fieldDefinition->fieldSettings
                    );

                    $attribute['validator-configuration'] = $fieldDefinition->validatorConfiguration;

                    $attributes[] = $attribute;
                }

                $contentTypeData = array_merge(
                    $contentTypeData,
                    array(
                        'name' => $contentType->getName($this->getLanguageCode()),
                        'description' => $contentType->getDescription($this->getLanguageCode()),
                        'name_pattern' => $contentType->nameSchema,
                        'url_name_pattern' => $contentType->urlAliasSchema,
                        'is_container' => $contentType->isContainer,
                        'lang' => $this->getLanguageCode(),
                        'attributes' => $attributes
                    )
                );
            }

            $data[] = $contentTypeData;
        }

        $this->loginUser($previousUserId);
        return $data;
    }

    /**
     * Helper function to create field definitions to be added to a new/existing content type.
     *
     * @todo Add translation support if needed
     * @param ContentTypeService $contentTypeService
     * @param array $attribute
     * @param string $contentTypeIdentifier
     * @return \eZ\Publish\API\Repository\Values\ContentType\FieldDefinitionCreateStruct
     * @throws \Exception
     */
    private function createFieldDefinition(ContentTypeService $contentTypeService, array $attribute, $contentTypeIdentifier)
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
                    /// @todo check that this works for all field types. Maybe we should use fromHash() on the field type,
                    ///       or, better, use the complexFieldManager?
                    $fieldDefinition->defaultValue = $value;
                    break;
                case 'field-settings':
                    $fieldDefinition->fieldSettings = $this->getFieldSettings($value, $attribute['type'], $contentTypeIdentifier);
                    break;
                case 'position':
                    $fieldDefinition->position = (int)$value;
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
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @return \eZ\Publish\API\Repository\Values\ContentType\FieldDefinitionUpdateStruct
     * @throws \Exception
     */
    private function updateFieldDefinition(ContentTypeService $contentTypeService, array $attribute, $fieldTypeIdentifier, $contentTypeIdentifier)
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
                    $fieldDefinitionUpdateStruct->fieldSettings = $this->getFieldSettings($value, $fieldTypeIdentifier, $contentTypeIdentifier);
                    break;
                case 'position':
                    $fieldDefinitionUpdateStruct->position = (int)$value;
                    break;
                case 'validator-configuration':
                    $fieldDefinitionUpdateStruct->validatorConfiguration = $value;
                    break;
            }
        }

        return $fieldDefinitionUpdateStruct;
    }

    private function getFieldSettings($value, $fieldTypeIdentifier, $contentTypeIdentifier)
    {
        // 1st update any references in the value array
        // q: shall we delegate this exclusively to the hashToFieldSettings call below ?
        if (is_array($value)) {
            $ret = array();
            foreach ($value as $key => $val)
            {
                $ret[$key] = $val;

                // we do NOT check for refs in field settings which are arrays, even though we could, maybe *should*...
                if (!is_array($val)) {
                    $ret[$key] = $this->extendedReferenceResolver->resolveReference($val);
                }
            }
        } else {
            $ret = $this->extendedReferenceResolver->resolveReference($value);
        }

        // then handle the conversion of the settings from Hash to Repo representation
        if ($this->complexFieldManager->managesFieldDefinition($fieldTypeIdentifier, $contentTypeIdentifier)) {
            $ret = $this->complexFieldManager->hashToFieldSettings($fieldTypeIdentifier, $contentTypeIdentifier, $value);
        }

        return $ret;
    }

    /**
     * Helper to find out if a Field is already defined in a ContentType
     *
     * @param ContentType $contentType
     * @param string $fieldIdentifier
     * @return \eZ\Publish\API\Repository\Values\ContentType\FieldDefinition|null
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

<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\ContentType\FieldDefinition;
use Kaliop\eZMigrationBundle\API\Collection\ContentTypeCollection;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\Core\Helper\SortConverter;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentTypeMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentTypeGroupMatcher;
use Kaliop\eZMigrationBundle\Core\FieldHandlerManager;
use JmesPath\Env as JmesPath;

/**
 * Handles content type migrations
 */
class ContentTypeManager extends RepositoryExecutor implements MigrationGeneratorInterface
{
    protected $supportedActions = array('create', 'load', 'update', 'delete');
    protected $supportedStepTypes = array('content_type');

    protected $contentTypeMatcher;
    protected $contentTypeGroupMatcher;
    // This resolver is used to resolve references in content-type settings definitions
    protected $extendedReferenceResolver;
    protected $fieldHandlerManager;
    protected $sortConverter;

    public function __construct(ContentTypeMatcher $matcher, ContentTypeGroupMatcher $contentTypeGroupMatcher,
                                ReferenceResolverInterface $extendedReferenceResolver, FieldHandlerManager $fieldHandlerManager,
                                SortConverter $sortConverter)
    {
        $this->contentTypeMatcher = $matcher;
        $this->contentTypeGroupMatcher = $contentTypeGroupMatcher;
        $this->extendedReferenceResolver = $extendedReferenceResolver;
        $this->fieldHandlerManager = $fieldHandlerManager;
        $this->sortConverter = $sortConverter;
    }

    /**
     * Method to handle the create operation of the migration instructions
     */
    protected function create($step)
    {
        foreach (array('identifier', 'content_type_group', 'name_pattern', 'name', 'attributes') as $key) {
            if (!isset($step->dsl[$key])) {
                throw new \Exception("The '$key' key is missing in a content type creation definition");
            }
        }

        $contentTypeService = $this->repository->getContentTypeService();

        $contentTypeGroupId = $step->dsl['content_type_group'];
        $contentTypeGroupId = $this->referenceResolver->resolveReference($contentTypeGroupId);
        $contentTypeGroup = $this->contentTypeGroupMatcher->matchOneByKey($contentTypeGroupId);

        $contentTypeCreateStruct = $contentTypeService->newContentTypeCreateStruct($step->dsl['identifier']);
        $contentTypeCreateStruct->mainLanguageCode = $this->getLanguageCode($step);

        // Object Name pattern
        $contentTypeCreateStruct->nameSchema = $step->dsl['name_pattern'];

        // set names for the content type
        $contentTypeCreateStruct->names = array(
            $this->getLanguageCode($step) => $step->dsl['name'],
        );

        if (isset($step->dsl['description'])) {
            // set description for the content type
            $contentTypeCreateStruct->descriptions = array(
                $this->getLanguageCode($step) => $step->dsl['description'],
            );
        }

        if (isset($step->dsl['url_name_pattern'])) {
            $contentTypeCreateStruct->urlAliasSchema = $step->dsl['url_name_pattern'];
        }

        if (isset($step->dsl['is_container'])) {
            $contentTypeCreateStruct->isContainer = $step->dsl['is_container'];
        }

        if (isset($step->dsl['default_always_available'])) {
            $contentTypeCreateStruct->defaultAlwaysAvailable = $step->dsl['default_always_available'];
        }

        if (isset($step->dsl['default_sort_field'])) {
            $contentTypeCreateStruct->defaultSortField = $this->sortConverter->hash2SortField($step->dsl['default_sort_field']);
        }

        if (isset($step->dsl['default_sort_order'])) {
            $contentTypeCreateStruct->defaultSortOrder = $this->sortConverter->hash2SortOrder($step->dsl['default_sort_order']);
        }

        // Add attributes
        // NB: seems like eZ gets mixed up if we pass some attributes with a position and some without...
        // We go out of our way to avoid collisions and preserve an order: fields without position go *last*
        $maxFieldDefinitionPos = 0;
        $fieldDefinitions = array();
        foreach ($step->dsl['attributes'] as $position => $attribute) {
            $fieldDefinition = $this->createFieldDefinition($contentTypeService, $attribute, $step->dsl['identifier'], $this->getLanguageCode($step));
            $maxFieldDefinitionPos = $fieldDefinition->position > $maxFieldDefinitionPos ? $fieldDefinition->position : $maxFieldDefinitionPos;
            $fieldDefinitions[] = $fieldDefinition;
        }
        foreach ($fieldDefinitions as $fieldDefinition) {
            if ($fieldDefinition->position == 0) {
                $fieldDefinition->position = ++$maxFieldDefinitionPos;
            }
            $contentTypeCreateStruct->addFieldDefinition($fieldDefinition);
        }

        try {
            // Publish new class
            $contentTypeDraft = $contentTypeService->createContentType($contentTypeCreateStruct, array($contentTypeGroup));
            $contentTypeService->publishContentTypeDraft($contentTypeDraft);

            // Set references
            $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeDraft->identifier);
            $this->setReferences($contentType, $step);
        } catch (InvalidArgumentException $e) {
            if (empty($step->dsl['update_if_exists'])) {
                throw $e;
            }
            $contentType = $this->update($step);
        }

        return $contentType;
    }

    protected function load($step)
    {
        $contentTypeCollection = $this->matchContentTypes('load', $step);

        // This check is already done in setReferences
        /*if (count($contentTypeCollection) > 1 && isset($step->dsl['references'])) {
            throw new \Exception("Can not execute Content Type load because multiple contents match, and a references section is specified in the dsl. References can be set when only 1 content matches");
        }*/

        $this->setReferences($contentTypeCollection, $step);

        return $contentTypeCollection;
    }

    /**
     * Method to handle the update operation of the migration instructions
     */
    protected function update($step)
    {
        $contentTypeCollection = $this->matchContentTypes('update', $step);

        if (count($contentTypeCollection) > 1 && array_key_exists('references', $step->dsl)) {
            throw new \Exception("Can not execute Content Type update because multiple types match, and a references section is specified in the dsl. References can be set when only 1 type matches");
        }

        if (count($contentTypeCollection) > 1 && array_key_exists('new_identifier', $step->dsl)) {
            throw new \Exception("Can not execute Content Type update because multiple roles match, and a new_identifier is specified in the dsl.");
        }

        $contentTypeService = $this->repository->getContentTypeService();
        foreach ($contentTypeCollection as $key => $contentType) {

            $contentTypeDraft = $contentTypeService->createContentTypeDraft($contentType);

            $contentTypeUpdateStruct = $contentTypeService->newContentTypeUpdateStruct();
            $contentTypeUpdateStruct->mainLanguageCode = $this->getLanguageCode($step);

            if (isset($step->dsl['new_identifier'])) {
                $contentTypeUpdateStruct->identifier = $step->dsl['new_identifier'];
            }

            if (isset($step->dsl['name'])) {
                $contentTypeUpdateStruct->names = array($this->getLanguageCode($step) => $step->dsl['name']);
            }

            if (isset($step->dsl['description'])) {
                $contentTypeUpdateStruct->descriptions = array(
                    $this->getLanguageCode($step) => $step->dsl['description'],
                );
            }

            if (isset($step->dsl['name_pattern'])) {
                $contentTypeUpdateStruct->nameSchema = $step->dsl['name_pattern'];
            }

            if (isset($step->dsl['url_name_pattern'])) {
                $contentTypeUpdateStruct->urlAliasSchema = $step->dsl['url_name_pattern'];
            }

            if (isset($step->dsl['is_container'])) {
                $contentTypeUpdateStruct->isContainer = $step->dsl['is_container'];
            }

            if (isset($step->dsl['default_sort_field'])) {
                $contentTypeUpdateStruct->defaultSortField = $this->sortConverter->hash2SortField($step->dsl['default_sort_field']);
            }

            if (isset($step->dsl['default_sort_order'])) {
                $contentTypeUpdateStruct->defaultSortOrder = $this->sortConverter->hash2SortOrder($step->dsl['default_sort_order']);
            }

            // Add/edit attributes
            if (isset($step->dsl['attributes'])) {
                // NB: seems like eZ gets mixed up if we pass some attributes with a position and some without...
                // We go out of our way to avoid collisions and preserve order
                $maxFieldDefinitionPos = count($contentType->fieldDefinitions);
                $newFieldDefinitions = array();
                foreach ($step->dsl['attributes'] as $attribute) {

                    $existingFieldDefinition = $this->contentTypeHasFieldDefinition($contentType, $attribute['identifier']);

                    if ($existingFieldDefinition) {
                        // Edit existing attribute
                        $fieldDefinitionUpdateStruct = $this->updateFieldDefinition(
                            $contentTypeService, $attribute, $attribute['identifier'], $contentType->identifier, $this->getLanguageCode($step)
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
                        $newFieldDefinition = $this->createFieldDefinition($contentTypeService, $attribute, $contentType->identifier, $this->getLanguageCode($step));
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
            if (isset($step->dsl['remove_attributes'])) {
                foreach ($step->dsl['remove_attributes'] as $attribute) {
                    $existingFieldDefinition = $this->contentTypeHasFieldDefinition($contentType, $attribute);
                    if ($existingFieldDefinition) {
                        $contentTypeService->removeFieldDefinition($contentTypeDraft, $existingFieldDefinition);
                    }
                }
            }

            $contentTypeService->updateContentTypeDraft($contentTypeDraft, $contentTypeUpdateStruct);
            $contentTypeService->publishContentTypeDraft($contentTypeDraft);

            // Set references
            if (isset($step->dsl['new_identifier'])) {
                $contentType = $contentTypeService->loadContentTypeByIdentifier($step->dsl['new_identifier']);
            } else {
                $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeDraft->identifier);
            }

            $contentTypeCollection[$key] = $contentType;
        }

        $this->setReferences($contentTypeCollection, $step);

        return $contentTypeCollection;
    }

    /**
     * Method to handle the delete operation of the migration instructions
     */
    protected function delete($step)
    {
        $contentTypeCollection = $this->matchContentTypes('delete', $step);

        $this->setReferences($contentTypeCollection, $step);

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
    protected function matchContentTypes($action, $step)
    {
        if (!isset($step->dsl['identifier']) && !isset($step->dsl['match'])) {
            throw new \Exception("The identifier of a content type or a match condition is required to $action it");
        }

        // Backwards compat
        if (isset($step->dsl['match'])) {
            $match = $step->dsl['match'];
        } else {
            $match = array('identifier' => $step->dsl['identifier']);
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($match);

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
    protected function setReferences($contentType, $step)
    {
        if (!array_key_exists('references', $step->dsl)) {
            return false;
        }

        $references = $this->setReferencesCommon($contentType, $step->dsl['references']);
        $contentType = $this->insureSingleEntity($contentType, $references);

        foreach ($references as $reference) {
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
                case 'default_always_available':
                    $value = $contentType->defaultAlwaysAvailable;
                    break;
                case 'default_sort_field':
                    $value = $contentType->defaultSortField;
                    break;
                case 'default_sort_order':
                    $value = $contentType->defaultSortOrder;
                    break;
                case 'is_container':
                    $value = $contentType->isContainer;
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
                    // allow to get the value of fields as well as their sub-parts
                    if (strpos($reference['attribute'], 'attributes.') === 0) {
                        $parts = explode('.', $reference['attribute']);
                        // totally not sure if this list of special chars is correct for what could follow a jmespath identifier...
                        // also what about quoted strings?
                        $fieldIdentifier = preg_replace('/[[(|&!{].*$/', '', $parts[1]);
                        $fieldDefinition = $contentType->getFieldDefinition($fieldIdentifier);
                        $hashValue = $this->fieldDefinitionToHash($contentType, $fieldDefinition, $step->context);
                        if (count($parts) == 2 && $fieldIdentifier === $parts[1]) {
                            throw new \InvalidArgumentException('Content Type Manager does not support setting references for attribute ' . $reference['attribute'] . ': please specify an attribute definition sub element');
                        }
                        $value = JmesPath::search(implode('.', array_slice($parts, 1)), array($fieldIdentifier => $hashValue));
                        break;
                    }

                    throw new \InvalidArgumentException('Content Type Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            $this->referenceResolver->addReference($reference['identifier'], $value, $overwrite);
        }

        return true;
    }


    /**
     * @param array $matchCondition
     * @param string $mode
     * @param array $context
     * @throws \Exception
     * @return array
     */
    public function generateMigration(array $matchCondition, $mode, array $context = array())
    {
        $previousUserId = $this->loginUser($this->getAdminUserIdentifierFromContext($context));
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

                $attributes = array();
                foreach ($contentType->getFieldDefinitions() as $i => $fieldDefinition) {
                    $attributes[] = $this->fieldDefinitionToHash($contentType, $fieldDefinition, $context);
                }

                $contentTypeData = array_merge(
                    $contentTypeData,
                    array(
                        'name' => $contentType->getName($this->getLanguageCodeFromContext($context)),
                        'description' => $contentType->getDescription($this->getLanguageCodeFromContext($context)),
                        'name_pattern' => $contentType->nameSchema,
                        'url_name_pattern' => $contentType->urlAliasSchema,
                        'is_container' => $contentType->isContainer,
                        'lang' => $this->getLanguageCodeFromContext($context),
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
     * @param ContentType $contentType
     * @param FieldDefinition $fieldDefinition
     * @param array $context
     * @return array
     */
    protected function fieldDefinitionToHash(ContentType $contentType, FieldDefinition $fieldDefinition, $context)
    {
        $fieldTypeService = $this->repository->getFieldTypeService();
        $fieldTypeIdentifier = $fieldDefinition->fieldTypeIdentifier;

        $attribute = array(
            'identifier' => $fieldDefinition->identifier,
            'type' => $fieldTypeIdentifier,
            'name' => $fieldDefinition->getName($this->getLanguageCodeFromContext($context)),
            'description' => (string)$fieldDefinition->getDescription($this->getLanguageCodeFromContext($context)),
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
            $attribute['default-value'] = $this->fieldHandlerManager->fieldValueToHash(
                $fieldTypeIdentifier, $contentType->identifier, $fieldDefinition->defaultValue
            );
        }

        $attribute['field-settings'] = $this->fieldHandlerManager->fieldSettingsToHash(
            $fieldTypeIdentifier, $contentType->identifier, $fieldDefinition->fieldSettings
        );

        $attribute['validator-configuration'] = $fieldDefinition->validatorConfiguration;

        return $attribute;
    }

    /**
     * Helper function to create field definitions to be added to a new/existing content type.
     *
     * @todo Add translation support if needed
     * @param ContentTypeService $contentTypeService
     * @param array $attribute
     * @param string $contentTypeIdentifier
     * @param string $lang
     * @return \eZ\Publish\API\Repository\Values\ContentType\FieldDefinitionCreateStruct
     * @throws \Exception
     */
    protected function createFieldDefinition(ContentTypeService $contentTypeService, array $attribute, $contentTypeIdentifier, $lang)
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
                    $fieldDefinition->names = array($lang => $value);
                    break;
                case 'description':
                    $fieldDefinition->descriptions = array($lang => $value);
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
                    ///       or, better, use the FieldHandlerManager?
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
     * @param string $lang
     * @return \eZ\Publish\API\Repository\Values\ContentType\FieldDefinitionUpdateStruct
     * @throws \Exception
     */
    protected function updateFieldDefinition(ContentTypeService $contentTypeService, array $attribute, $fieldTypeIdentifier, $contentTypeIdentifier, $lang)
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
                    $fieldDefinitionUpdateStruct->names = array($lang => $value);
                    break;
                case 'description':
                    $fieldDefinitionUpdateStruct->descriptions = array($lang => $value);
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

    protected function getFieldSettings($value, $fieldTypeIdentifier, $contentTypeIdentifier)
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
        if ($this->fieldHandlerManager->managesFieldDefinition($fieldTypeIdentifier, $contentTypeIdentifier)) {
            $ret = $this->fieldHandlerManager->hashToFieldSettings($fieldTypeIdentifier, $contentTypeIdentifier, $ret);
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
    protected function contentTypeHasFieldDefinition(ContentType $contentType, $fieldIdentifier)
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

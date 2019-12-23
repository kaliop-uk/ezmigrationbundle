<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\ContentType\FieldDefinition;
use eZ\Publish\API\Repository\Values\Content\Location;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\ContentCreateStruct;
use eZ\Publish\API\Repository\Values\Content\ContentUpdateStruct;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\API\EnumerableMatcherInterface;
use Kaliop\eZMigrationBundle\Core\FieldHandlerManager;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\SectionMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\UserMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateGroupMatcher;
use Kaliop\eZMigrationBundle\Core\Helper\SortConverter;
use JmesPath\Env as JmesPath;

/**
 * Handles content migrations.
 *
 * @todo add support for updating of content metadata
 */
class ContentManager extends RepositoryExecutor implements MigrationGeneratorInterface, EnumerableMatcherInterface
{
    protected $supportedStepTypes = array('content');
    protected $supportedActions = array('create', 'load', 'update', 'delete');

    protected $contentMatcher;
    protected $sectionMatcher;
    protected $userMatcher;
    protected $objectStateMatcher;
    protected $objectStateGroupMatcher;
    protected $fieldHandlerManager;
    protected $locationManager;
    protected $sortConverter;

    // these are not exported when generating a migration
    protected $ignoredStateGroupIdentifiers = array('ez_lock');

    public function __construct(
        ContentMatcher $contentMatcher,
        SectionMatcher $sectionMatcher,
        UserMatcher $userMatcher,
        ObjectStateMatcher $objectStateMatcher,
        ObjectStateGroupMatcher $objectStateGroupMatcher,
        FieldHandlerManager $fieldHandlerManager,
        LocationManager $locationManager,
        SortConverter $sortConverter
    ) {
        $this->contentMatcher = $contentMatcher;
        $this->sectionMatcher = $sectionMatcher;
        $this->userMatcher = $userMatcher;
        $this->objectStateMatcher = $objectStateMatcher;
        $this->objectStateGroupMatcher = $objectStateGroupMatcher;
        $this->fieldHandlerManager = $fieldHandlerManager;
        $this->locationManager = $locationManager;
        $this->sortConverter = $sortConverter;
    }

    /**
     * Handles the content create migration action type
     */
    protected function create($step)
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();

        $contentTypeIdentifier = $step->dsl['content_type'];
        $contentTypeIdentifier = $this->referenceResolver->resolveReference($contentTypeIdentifier);
        /// @todo use a contenttypematcher
        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);

        $contentCreateStruct = $contentService->newContentCreateStruct($contentType, $this->getLanguageCode($step));

        $this->setFields($contentCreateStruct, $step->dsl['attributes'], $contentType, $step);

        if (isset($step->dsl['always_available'])) {
            $contentCreateStruct->alwaysAvailable = $step->dsl['always_available'];
        } else {
            // Could be removed when https://github.com/ezsystems/ezpublish-kernel/pull/1874 is merged,
            // but we strive to support old eZ kernel versions as well...
            $contentCreateStruct->alwaysAvailable = $contentType->defaultAlwaysAvailable;
        }

        if (isset($step->dsl['remote_id'])) {
            $contentCreateStruct->remoteId = $step->dsl['remote_id'];
        }

        if (isset($step->dsl['section'])) {
            $sectionKey = $this->referenceResolver->resolveReference($step->dsl['section']);
            $section = $this->sectionMatcher->matchOneByKey($sectionKey);
            $contentCreateStruct->sectionId = $section->id;
        }

        if (isset($step->dsl['owner'])) {
            $owner = $this->getUser($step->dsl['owner']);
            $contentCreateStruct->ownerId = $owner->id;
        }

        // This is a bit tricky, as the eZPublish API does not support having a different creator and owner with only 1 version.
        // We allow it, hoping that nothing gets broken because of it
        if (isset($step->dsl['version_creator'])) {
            $realContentOwnerId = $contentCreateStruct->ownerId;
            if ($realContentOwnerId == null) {
                $realContentOwnerId = $this->repository->getCurrentUser()->id;
            }
            $versionCreator = $this->getUser($step->dsl['version_creator']);
            $contentCreateStruct->ownerId = $versionCreator->id;
        }

        if (isset($step->dsl['modification_date'])) {
            $contentCreateStruct->modificationDate = $this->toDateTime($step->dsl['modification_date']);
        }

        // instantiate a location create struct from the parent location:
        // BC
        $locationId = isset($step->dsl['parent_location']) ? $step->dsl['parent_location'] : (
            isset($step->dsl['main_location']) ? $step->dsl['main_location'] : null
        );
        // 1st resolve references
        $locationId = $this->referenceResolver->resolveReference($locationId);
        // 2nd allow to specify the location via remote_id
        $locationId = $this->locationManager->matchLocationByKey($locationId)->id;
        $locationCreateStruct = $locationService->newLocationCreateStruct($locationId);

        if (isset($step->dsl['location_remote_id'])) {
            $locationCreateStruct->remoteId = $step->dsl['location_remote_id'];
        }

        if (isset($step->dsl['priority'])) {
            $locationCreateStruct->priority = $step->dsl['priority'];
        }

        if (isset($step->dsl['is_hidden'])) {
            $locationCreateStruct->hidden = $step->dsl['is_hidden'];
        }

        if (isset($step->dsl['sort_field'])) {
            $locationCreateStruct->sortField = $this->sortConverter->hash2SortField($step->dsl['sort_field']);
        } else {
            $locationCreateStruct->sortField = $contentType->defaultSortField;
        }

        if (isset($step->dsl['sort_order'])) {
            $locationCreateStruct->sortOrder = $this->sortConverter->hash2SortOrder($step->dsl['sort_order']);
        } else {
            $locationCreateStruct->sortOrder = $contentType->defaultSortOrder;
        }

        $locations = array($locationCreateStruct);

        // BC
        $other_locations = isset($step->dsl['other_parent_locations']) ? $step->dsl['other_parent_locations'] : (
            isset($step->dsl['other_locations']) ? $step->dsl['other_locations'] : null
        );
        if (isset($other_locations)) {
            foreach ($other_locations as $locationId) {
                $locationId = $this->referenceResolver->resolveReference($locationId);
                $locationId = $this->locationManager->matchLocationByKey($locationId)->id;
                $secondaryLocationCreateStruct = $locationService->newLocationCreateStruct($locationId);
                array_push($locations, $secondaryLocationCreateStruct);
            }
        }

        // create a draft using the content and location create struct and publish it
        $draft = $contentService->createContent($contentCreateStruct, $locations);
        $content = $contentService->publishVersion($draft->versionInfo);

        if (isset($step->dsl['object_states'])) {
            $this->setObjectStates($content, $step->dsl['object_states']);
        }

        // 2nd part of the hack: re-set the content owner to its intended value
        if (isset($step->dsl['version_creator']) || isset($step->dsl['publication_date'])) {
            $contentMetaDataUpdateStruct = $contentService->newContentMetadataUpdateStruct();

            if (isset($step->dsl['version_creator'])) {
                $contentMetaDataUpdateStruct->ownerId = $realContentOwnerId;
            }
            if (isset($step->dsl['publication_date'])) {
                $contentMetaDataUpdateStruct->publishedDate = $this->toDateTime($step->dsl['publication_date']);
            }
            // we have to do this to make sure we preserve the custom modification date
            if (isset($step->dsl['modification_date'])) {
                $contentMetaDataUpdateStruct->modificationDate = $this->toDateTime($step->dsl['modification_date']);
            }

            $contentService->updateContentMetadata($content->contentInfo, $contentMetaDataUpdateStruct);
        }

        $this->setReferences($content, $step);

        return $content;
    }

    protected function load($step)
    {
        $contentCollection = $this->matchContents('load', $step);

        $this->setReferences($contentCollection, $step);

        return $contentCollection;
    }

    /**
     * Handles the content update migration action type
     *
     * @todo handle updating of more metadata fields
     */
    protected function update($step)
    {
        $contentService = $this->repository->getContentService();
        $contentTypeService = $this->repository->getContentTypeService();

        $contentCollection = $this->matchContents('update', $step);

        if (count($contentCollection) > 1 && isset($step->dsl['references'])) {
            throw new \Exception("Can not execute Content update because multiple contents match, and a references section is specified in the dsl. References can be set when only 1 content matches");
        }

        if (count($contentCollection) > 1 && isset($step->dsl['main_location'])) {
            throw new \Exception("Can not execute Content update because multiple contents match, and a main_location section is specified in the dsl. References can be set when only 1 content matches");
        }

        $contentType = array();

        foreach ($contentCollection as $key => $content) {
            $contentInfo = $content->contentInfo;

            if (!isset($contentType[$contentInfo->contentTypeId])) {
                $contentType[$contentInfo->contentTypeId] = $contentTypeService->loadContentType($contentInfo->contentTypeId);
            }

            if (isset($step->dsl['attributes']) || isset($step->dsl['version_creator'])) {
                $contentUpdateStruct = $contentService->newContentUpdateStruct();

                if (isset($step->dsl['attributes'])) {
                    $this->setFields($contentUpdateStruct, $step->dsl['attributes'], $contentType[$contentInfo->contentTypeId], $step);
                }

                $versionCreator = null;
                if (isset($step->dsl['version_creator'])) {
                    $versionCreator = $this->getUser($step->dsl['version_creator']);
                }

                $draft = $contentService->createContentDraft($contentInfo, null, $versionCreator);
                $contentService->updateContent($draft->versionInfo, $contentUpdateStruct);
                $content = $contentService->publishVersion($draft->versionInfo);
            }

            if (isset($step->dsl['always_available']) ||
                isset($step->dsl['new_remote_id']) ||
                isset($step->dsl['owner']) ||
                isset($step->dsl['modification_date']) ||
                isset($step->dsl['publication_date'])) {

                $contentMetaDataUpdateStruct = $contentService->newContentMetadataUpdateStruct();

                if (isset($step->dsl['always_available'])) {
                    $contentMetaDataUpdateStruct->alwaysAvailable = $step->dsl['always_available'];
                }

                if (isset($step->dsl['new_remote_id'])) {
                    $contentMetaDataUpdateStruct->remoteId = $step->dsl['new_remote_id'];
                }

                if (isset($step->dsl['owner'])) {
                    $owner = $this->getUser($step->dsl['owner']);
                    $contentMetaDataUpdateStruct->ownerId = $owner->id;
                }

                if (isset($step->dsl['modification_date'])) {
                    $contentMetaDataUpdateStruct->modificationDate = $this->toDateTime($step->dsl['modification_date']);
                }

                if (isset($step->dsl['publication_date'])) {
                    $contentMetaDataUpdateStruct->publishedDate = $this->toDateTime($step->dsl['publication_date']);
                }

                $content = $contentService->updateContentMetadata($content->contentInfo, $contentMetaDataUpdateStruct);
            }

            if (isset($step->dsl['section'])) {
                $this->setSection($content, $step->dsl['section']);
            }

            if (isset($step->dsl['object_states'])) {
                $this->setObjectStates($content, $step->dsl['object_states']);
            }

            if (isset($step->dsl['main_location'])) {
                $this->setMainLocation($content, $step->dsl['main_location']);

            }
            $contentCollection[$key] = $content;
        }

        $this->setReferences($contentCollection, $step);

        return $contentCollection;
    }

    /**
     * Handles the content delete migration action type
     */
    protected function delete($step)
    {
        $contentCollection = $this->matchContents('delete', $step);

        $this->setReferences($contentCollection, $step);

        $contentService = $this->repository->getContentService();

        foreach ($contentCollection as $content) {
            try {
                $contentService->deleteContent($content->contentInfo);
            } catch (NotFoundException $e) {
                // Someone else (or even us, by virtue of location tree?) removed the content which we found just a
                // second ago. We can safely ignore this
            }
        }

        return $contentCollection;
    }

    /**
     * @param string $action
     * @return ContentCollection
     * @throws \Exception
     */
    protected function matchContents($action, $step)
    {
        if (!isset($step->dsl['object_id']) && !isset($step->dsl['remote_id']) && !isset($step->dsl['match'])) {
            throw new \Exception("The id or remote id of an object or a match condition is required to $action a content");
        }

        // Backwards compat

        if (isset($step->dsl['match'])) {
            $match = $step->dsl['match'];
        } else {
            if (isset($step->dsl['object_id'])) {
                $match = array('content_id' => $step->dsl['object_id']);
            } elseif (isset($step->dsl['remote_id'])) {
                $match = array('content_remote_id' => $step->dsl['remote_id']);
            }
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($match);

        $offset = isset($step->dsl['match_offset']) ? $this->referenceResolver->resolveReference($step->dsl['match_offset']) : 0;
        $limit = isset($step->dsl['match_limit']) ? $this->referenceResolver->resolveReference($step->dsl['match_limit']) : 0;
        $sort = isset($step->dsl['match_sort']) ? $this->referenceResolver->resolveReference($step->dsl['match_sort']) : array();

        return $this->contentMatcher->match($match, $sort, $offset, $limit);
    }

    /**
     * @param Content $content
     * @param array $references the definitions of the references to set
     * @throws \InvalidArgumentException When trying to assign a reference to an unsupported attribute
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($content, array $references, $step)
    {
        $refs = array();

        foreach ($references as $reference) {

            switch ($reference['attribute']) {
                case 'object_id':
                case 'content_id':
                case 'id':
                    $value = $content->id;
                    break;
                case 'remote_id':
                case 'content_remote_id':
                    $value = $content->contentInfo->remoteId;
                    break;
                case 'always_available':
                    $value = $content->contentInfo->alwaysAvailable;
                    break;
                case 'content_type_id':
                    $value = $content->contentInfo->contentTypeId;
                    break;
                case 'content_type_identifier':
                    $contentTypeService = $this->repository->getContentTypeService();
                    $value = $contentTypeService->loadContentType($content->contentInfo->contentTypeId)->identifier;
                    break;
                case 'current_version':
                case 'current_version_no':
                    $value = $content->contentInfo->currentVersionNo;
                    break;
                case 'location_id':
                case 'main_location_id':
                    $value = $content->contentInfo->mainLocationId;
                    break;
                case 'location_remote_id':
                    $locationService = $this->repository->getLocationService();
                    $value = $locationService->loadLocation($content->contentInfo->mainLocationId)->remoteId;
                    break;
                case 'main_language_code':
                    $value = $content->contentInfo->mainLanguageCode;
                    break;
                case 'modification_date':
                    $value = $content->contentInfo->modificationDate->getTimestamp();
                    break;
                case 'name':
                    $value = $content->contentInfo->name;
                    break;
                case 'owner_id':
                    $value = $content->contentInfo->ownerId;
                    break;
                case 'path':
                    $locationService = $this->repository->getLocationService();
                    $value = $locationService->loadLocation($content->contentInfo->mainLocationId)->pathString;
                    break;
                case 'publication_date':
                    $value = $content->contentInfo->publishedDate->getTimestamp();
                    break;
                case 'section_id':
                    $value = $content->contentInfo->sectionId;
                    break;
                case 'section_identifier':
                    $sectionService = $this->repository->getSectionService();
                    $value = $sectionService->loadSection($content->contentInfo->sectionId)->identifier;
                    break;
                case 'version_count':
                    $contentService = $this->repository->getContentService();
                    $value = count($contentService->loadVersions($content->contentInfo));
                    break;
                default:
                    if (strpos($reference['attribute'], 'object_state.') === 0) {
                        $stateGroupKey = substr($reference['attribute'], 13);
                        $stateGroup = $this->objectStateGroupMatcher->matchOneByKey($stateGroupKey);
                        $value = $stateGroupKey . '/' . $this->repository->getObjectStateService()->
                            getContentState($content->contentInfo, $stateGroup)->identifier;
                        break;
                    }

                    // allow to get the value of fields as well as their sub-parts
                    if (strpos($reference['attribute'], 'attributes.') === 0) {
                        $contentType = $this->repository->getContentTypeService()->loadContentType(
                            $content->contentInfo->contentTypeId
                        );
                        $parts = explode('.', $reference['attribute']);
                        // totally not sure if this list of special chars is correct for what could follow a jmespath identifier...
                        // also what about quoted strings?
                        $fieldIdentifier = preg_replace('/[[(|&!{].*$/', '', $parts[1]);
                        $field = $content->getField($fieldIdentifier);
                        $fieldDefinition = $contentType->getFieldDefinition($fieldIdentifier);
                        $hashValue = $this->fieldHandlerManager->fieldValueToHash(
                            $fieldDefinition->fieldTypeIdentifier, $contentType->identifier, $field->value
                        );
                        if (is_array($hashValue)) {
                            if (count($parts) == 2 && $fieldIdentifier === $parts[1]) {
                                throw new \InvalidArgumentException('Content Manager does not support setting references for attribute ' . $reference['attribute'] . ': the given attribute has an array value');
                            }
                            $value = JmesPath::search(implode('.', array_slice($parts, 1)), array($fieldIdentifier => $hashValue));
                        } else {
                            if (count($parts) > 2) {
                                throw new \InvalidArgumentException('Content Manager does not support setting references for attribute ' . $reference['attribute'] . ': the given attribute has a scalar value');
                            }
                            $value = $hashValue;
                        }
                        break;
                    }

                    throw new \InvalidArgumentException('Content Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }

    /**
     * @param array $matchCondition
     * @param string $mode
     * @param array $context
     * @throws \Exception
     * @return array
     *
     * @todo add support for dumping all object languages
     * @todo add 2ndary locations when in 'update' mode
     * @todo add dumping of sort_field and sort_order for 2ndary locations
     */
    public function generateMigration(array $matchCondition, $mode, array $context = array())
    {
        $currentUser = $this->authenticateUserByContext($context);
        $contentCollection = $this->contentMatcher->match($matchCondition);
        $data = array();

        /** @var \eZ\Publish\API\Repository\Values\Content\Content $content */
        foreach ($contentCollection as $content) {

            $location = $this->repository->getLocationService()->loadLocation($content->contentInfo->mainLocationId);
            $contentType = $this->repository->getContentTypeService()->loadContentType(
                $content->contentInfo->contentTypeId
            );

            $contentData = array(
                'type' => reset($this->supportedStepTypes),
                'mode' => $mode
            );

            switch ($mode) {
                case 'create':
                    $contentData = array_merge(
                        $contentData,
                        array(
                            'content_type' => $contentType->identifier,
                            'parent_location' => $location->parentLocationId,
                            'priority' => $location->priority,
                            'is_hidden' => $location->invisible,
                            'sort_field' => $this->sortConverter->sortField2Hash($location->sortField),
                            'sort_order' => $this->sortConverter->sortOrder2Hash($location->sortOrder),
                            'remote_id' => $content->contentInfo->remoteId,
                            'location_remote_id' => $location->remoteId,
                            'section' => $content->contentInfo->sectionId,
                            'object_states' => $this->getObjectStates($content),
                        )
                    );
                    $locationService = $this->repository->getLocationService();
                    /// @todo for accurate replication, we should express the addinfg of 2ndary locatins as separate steps, and copy over visibility, priority etc
                    $locations = $locationService->loadLocations($content->contentInfo);
                    if (count($locations) > 1) {
                        $otherParentLocations = array();
                        foreach($locations as $otherLocation) {
                            if ($otherLocation->id != $location->id) {
                                $otherParentLocations[] = $otherLocation->parentLocationId;
                            }
                        }
                        $contentData['other_parent_locations'] = $otherParentLocations;
                    }
                    break;
                case 'update':
                    $contentData = array_merge(
                        $contentData,
                        array(
                            'match' => array(
                                ContentMatcher::MATCH_CONTENT_REMOTE_ID => $content->contentInfo->remoteId
                            ),
                            'new_remote_id' => $content->contentInfo->remoteId,
                            'section' => $content->contentInfo->sectionId,
                            'object_states' => $this->getObjectStates($content),
                        )
                    );
                    break;
                case 'delete':
                    $contentData = array_merge(
                        $contentData,
                        array(
                            'match' => array(
                                ContentMatcher::MATCH_CONTENT_REMOTE_ID => $content->contentInfo->remoteId
                            )
                        )
                    );
                    break;
                default:
                    throw new \Exception("Executor 'content' doesn't support mode '$mode'");
            }

            if ($mode != 'delete') {

                $attributes = array();
                foreach ($content->getFieldsByLanguage($this->getLanguageCodeFromContext($context)) as $fieldIdentifier => $field) {
                    $fieldDefinition = $contentType->getFieldDefinition($fieldIdentifier);
                    $attributes[$field->fieldDefIdentifier] = $this->fieldHandlerManager->fieldValueToHash(
                        $fieldDefinition->fieldTypeIdentifier, $contentType->identifier, $field->value
                    );
                }

                $contentData = array_merge(
                    $contentData,
                    array(
                        'lang' => $this->getLanguageCodeFromContext($context),
                        'section' => $content->contentInfo->sectionId,
                        'owner' => $content->contentInfo->ownerId,
                        'modification_date' => $content->contentInfo->modificationDate->getTimestamp(),
                        'publication_date' => $content->contentInfo->publishedDate->getTimestamp(),
                        'always_available' => (bool)$content->contentInfo->alwaysAvailable,
                        'attributes' => $attributes
                    )
                );
            }

            $data[] = $contentData;
        }

        $this->authenticateUserByReference($currentUser);
        return $data;
    }

    /**
     * @return string[]
     */
    public function listAllowedConditions()
    {
        return $this->contentMatcher->listAllowedConditions();
    }

    /**
     * Helper function to set the fields of a ContentCreateStruct based on the DSL attribute settings.
     *
     * @param ContentCreateStruct|ContentUpdateStruct $createOrUpdateStruct
     * @param array $fields see description of expected format in code below
     * @param ContentType $contentType
     * @param $step
     * @throws \Exception
     */
    protected function setFields($createOrUpdateStruct, array $fields, ContentType $contentType, $step)
    {
        $fields = $this->normalizeFieldDefs($fields, $step);

        foreach ($fields as $fieldIdentifier => $fieldLanguages) {
            foreach ($fieldLanguages as $language => $fieldValue) {
                if (!$contentType->getFieldDefinition($fieldIdentifier)) {
                    throw new \Exception("Field '$fieldIdentifier' is not present in content type '{$contentType->identifier}'");
                }

                $fieldDefinition = $contentType->getFieldDefinition($fieldIdentifier);
                $fieldValue = $this->getFieldValue($fieldValue, $fieldDefinition, $contentType->identifier, $step->context);
                $createOrUpdateStruct->setField($fieldIdentifier, $fieldValue, $language);
            }
        }
    }

    /**
     * Helper function to accommodate the definition of fields
     * - using a legacy DSL version
     * - using either single-language or multi-language style
     *
     * @param array $fields
     * @return array
     */
    protected function normalizeFieldDefs($fields, $step)
    {
        $convertedFields = [];
        $i = 0;
        // the 'easy' yml: key = field name, value = value
        // deprecated: the 'legacy' yml: key = numerical index, value = array ( field name => value )
        foreach ($fields as $key => $field) {
            if ($key === $i && is_array($field) && count($field) == 1) {
                // each $field is one key value pair
                // eg.: $field = array($fieldIdentifier => $fieldValue)
                reset($field);
                $fieldIdentifier = key($field);
                $fieldValue = $field[$fieldIdentifier];

                $convertedFields[$fieldIdentifier] = $fieldValue;
            } else {
                $convertedFields[$key] = $field;
            }
            $i++;
        }

        // transform single-language field defs in multilang ones
        if (!$this->hasLanguageCodesAsKeys($convertedFields)) {
            $language = $this->getLanguageCode($step);

            foreach ($convertedFields as $fieldIdentifier => $fieldValue) {
                $convertedFields[$fieldIdentifier] = array($language => $fieldValue);
            }
        }

        return $convertedFields;
    }

    /**
     * Checks whether all fields are using multilang syntax ie. a valid language as key.
     *
     * @param array $fields
     * @return bool
     */
    protected function hasLanguageCodesAsKeys(array $fields)
    {
        $languageCodes = $this->getContentLanguageCodes();

        foreach ($fields as $fieldIdentifier => $fieldData) {
            if (!is_array($fieldData) || empty($fieldData)) {
                return false;
            }

            foreach ($fieldData as $key => $data) {
                if (!in_array($key, $languageCodes, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Returns all enabled Languages in the repo.
     * @todo move to parent class?
     *
     * @return string[]
     */
    protected function getContentLanguageCodes()
    {
        return array_map(
            function($language) {
                return $language->languageCode;
            },
            array_filter(
                $this->repository->getContentLanguageService()->loadLanguages(),
                function ($language) {
                    return $language->enabled;
                }
            )
        );
    }

    protected function setSection(Content $content, $sectionKey)
    {
        $sectionKey = $this->referenceResolver->resolveReference($sectionKey);
        $section = $this->sectionMatcher->matchOneByKey($sectionKey);

        $sectionService = $this->repository->getSectionService();
        $sectionService->assignSection($content->contentInfo, $section);
    }

    protected function setObjectStates(Content $content, array $stateKeys)
    {
        foreach ($stateKeys as $stateKey) {
            $stateKey = $this->referenceResolver->resolveReference($stateKey);
            /** @var \eZ\Publish\API\Repository\Values\ObjectState\ObjectState $state */
            $state = $this->objectStateMatcher->matchOneByKey($stateKey);

            $stateService = $this->repository->getObjectStateService();
            $stateService->setContentState($content->contentInfo, $state->getObjectStateGroup(), $state);
        }
    }

    protected function setMainLocation(Content $content, $locationId)
    {
        $locationId = $this->referenceResolver->resolveReference($locationId);
        if (is_int($locationId) || ctype_digit($locationId)) {
            $location = $this->repository->getLocationService()->loadLocation($locationId);
        } else {
            $location = $this->repository->getLocationService()->loadLocationByRemoteId($locationId);
        }

        if ($location->contentInfo->id != $content->id) {
            throw new \Exception("Can not set main location {$location->id} to content {$content->id} as it belongs to another object");
        }

        $contentService = $this->repository->getContentService();
        $contentMetaDataUpdateStruct = $contentService->newContentMetadataUpdateStruct();
        $contentMetaDataUpdateStruct->mainLocationId = $location->id;
        $contentService->updateContentMetadata($location->contentInfo, $contentMetaDataUpdateStruct);
    }

    protected function getObjectStates(Content $content)
    {
        $states = [];

        $objectStateService = $this->repository->getObjectStateService();
        $groups = $objectStateService->loadObjectStateGroups();
        foreach ($groups as $group) {
            if (in_array($group->identifier, $this->ignoredStateGroupIdentifiers)) {
                continue;
            }
            $state = $objectStateService->getContentState($content->contentInfo, $group);
            $states[] = $group->identifier . '/' . $state->identifier;
        }

        return $states;
    }

    /**
     * Create the field value from the migration definition hash
     *
     * @param mixed $value
     * @param FieldDefinition $fieldDefinition
     * @param string $contentTypeIdentifier
     * @param array $context
     * @throws \InvalidArgumentException
     * @return mixed
     */
    protected function getFieldValue($value, FieldDefinition $fieldDefinition, $contentTypeIdentifier, array $context = array())
    {
        $fieldTypeIdentifier = $fieldDefinition->fieldTypeIdentifier;

        if (is_array($value) || $this->fieldHandlerManager->managesField($fieldTypeIdentifier, $contentTypeIdentifier)) {
            // since we now allow refs to be arrays, let's attempt a 1st pass at resolving them here instead of every single fieldHandler...
            if (is_string($value) && $this->fieldHandlerManager->doPreResolveStringReferences($fieldTypeIdentifier, $contentTypeIdentifier)) {
                $value = $this->referenceResolver->resolveReference($value);
            }
            // inject info about the current content type and field into the context
            $context['contentTypeIdentifier'] = $contentTypeIdentifier;
            $context['fieldIdentifier'] = $fieldDefinition->identifier;
            return $this->fieldHandlerManager->hashToFieldValue($fieldTypeIdentifier, $contentTypeIdentifier, $value, $context);
        }

        return $this->getSingleFieldValue($value, $fieldDefinition, $contentTypeIdentifier, $context);
    }

    /**
     * Create the field value for a primitive field from the migration definition hash
     *
     * @param mixed $value
     * @param FieldDefinition $fieldDefinition
     * @param string $contentTypeIdentifier
     * @param array $context
     * @throws \InvalidArgumentException
     * @return mixed
     */
    protected function getSingleFieldValue($value, FieldDefinition $fieldDefinition, $contentTypeIdentifier, array $context = array())
    {
        // booleans were handled here. They are now handled as complextypes

        // q: do we really want this to happen by default on all scalar field values?
        // Note: if you want this *not* to happen, register a complex field for your scalar field...
        $value = $this->referenceResolver->resolveReference($value);

        return $value;
    }

    /**
     * Load user using either login, email, id - resolving eventual references
     * @param int|string $userKey
     * @return \eZ\Publish\API\Repository\Values\User\User
     */
    protected function getUser($userKey)
    {
        $userKey = $this->referenceResolver->resolveReference($userKey);
        return $this->userMatcher->matchOneByKey($userKey);
    }

    /**
     * @param int|string $date if integer, we assume a timestamp
     * @return \DateTime
     */
    protected function toDateTime($date)
    {
        if (is_int($date)) {
            return new \DateTime("@" . $date);
        } else {
            return new \DateTime($date);
        }
    }
}

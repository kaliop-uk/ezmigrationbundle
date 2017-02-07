<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\ContentType\FieldDefinition;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\ContentCreateStruct;
use eZ\Publish\API\Repository\Values\Content\ContentUpdateStruct;
use eZ\Publish\Core\FieldType\Checkbox\Value as CheckboxValue;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\Core\ComplexField\ComplexFieldManager;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\SectionMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\UserMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateMatcher;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;

/**
 * Implements the actions for managing (create/update/delete) Content in the system through
 * migrations and abstracts away the eZ Publish Public API.
 *
 * @todo add support for updating of content metadata
 */
class ContentManager extends RepositoryExecutor implements MigrationGeneratorInterface
{
    protected $supportedStepTypes = array('content');

    protected $contentMatcher;
    protected $sectionMatcher;
    protected $userMatcher;
    protected $objectStateMatcher;
    protected $complexFieldManager;
    protected $locationManager;

    public function __construct(
        ContentMatcher $contentMatcher,
        SectionMatcher $sectionMatcher,
        UserMatcher $userMatcher,
        ObjectStateMatcher $objectStateMatcher,
        ComplexFieldManager $complexFieldManager,
        LocationManager $locationManager
    ) {
        $this->contentMatcher = $contentMatcher;
        $this->sectionMatcher = $sectionMatcher;
        $this->userMatcher = $userMatcher;
        $this->objectStateMatcher = $objectStateMatcher;
        $this->complexFieldManager = $complexFieldManager;
        $this->locationManager = $locationManager;
    }

    /**
     * Handle the content create migration action type
     */
    protected function create()
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();
        $sectionService = $this->repository->getSectionService();

        $contentTypeIdentifier = $this->dsl['content_type'];
        $contentTypeIdentifier = $this->referenceResolver->resolveReference($contentTypeIdentifier);
        /// @todo use a contenttypematcher
        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);

        $contentCreateStruct = $contentService->newContentCreateStruct($contentType, $this->getLanguageCode());

        $this->setFields($contentCreateStruct, $this->dsl['attributes'], $contentType);

        if (isset($this->dsl['always_available'])) {
            $contentCreateStruct->alwaysAvailable = $this->dsl['always_available'];
        } else {
            // Could be removed when https://github.com/ezsystems/ezpublish-kernel/pull/1874 is merged,
            // but we strive to support old eZ kernel versions as well...
            $contentCreateStruct->alwaysAvailable = $contentType->defaultAlwaysAvailable;
        }

        if (isset($this->dsl['remote_id'])) {
            $contentCreateStruct->remoteId = $this->dsl['remote_id'];
        }

        if (isset($this->dsl['section'])) {
            $sectionId = $this->dsl['section'];
            try {
                $section = $sectionService->loadSectionByIdentifier($sectionId);
                $sectionId = $section->id;
            }
            catch (\eZ\Publish\API\Repository\Exceptions\NotFoundException $notFoundException) {
                $sectionId = $this->referenceResolver->resolveReference($sectionId);
            }
            $contentCreateStruct->sectionId = $sectionId;
        }

        if (isset($this->dsl['owner'])) {
            $owner = $this->getUser($this->dsl['owner']);
            $contentCreateStruct->ownerId = $owner->id;
        }

        // This is a bit tricky, as the eZPublish API does not support having a different creator and owner with only 1 version.
        // We allow it, hoping that nothing gets broken because of it
        if (isset($this->dsl['version_creator'])) {
            $realContentOwnerId = $contentCreateStruct->ownerId;
            if ($realContentOwnerId == null) {
                $realContentOwnerId = $this->repository->getCurrentUser()->id;
            }
            $versionCreator = $this->getUser($this->dsl['version_creator']);
            $contentCreateStruct->ownerId = $versionCreator->id;
        }

        if (isset($this->dsl['modification_date'])) {
            $contentCreateStruct->modificationDate = $this->toDateTime($this->dsl['modification_date']);
        }

        // instantiate a location create struct from the parent location:
        // BC
        $locationId = isset($this->dsl['parent_location']) ? $this->dsl['parent_location'] : (
            isset($this->dsl['main_location']) ? $this->dsl['main_location'] : null
        );
        // 1st resolve references
        $locationId = $this->referenceResolver->resolveReference($locationId);
        // 2nd allow to specify the location via remote_id
        $locationId = $this->locationManager->matchLocationByKey($locationId)->id;
        $locationCreateStruct = $locationService->newLocationCreateStruct($locationId);

        if (isset($this->dsl['location_remote_id'])) {
            $locationCreateStruct->remoteId = $this->dsl['location_remote_id'];
        }

        if (isset($this->dsl['priority'])) {
            $locationCreateStruct->priority = $this->dsl['priority'];
        }

        if (isset($this->dsl['is_hidden'])) {
            $locationCreateStruct->hidden = $this->dsl['is_hidden'];
        }

        if (isset($this->dsl['sort_field'])) {
            $locationCreateStruct->sortField = $this->locationManager->getSortField($this->dsl['sort_field']);
        } else {
            $locationCreateStruct->sortField = $contentType->defaultSortField;
        }

        if (isset($this->dsl['sort_order'])) {
            $locationCreateStruct->sortOrder = $this->locationManager->getSortOrder($this->dsl['sort_order']);
        } else {
            $locationCreateStruct->sortOrder = $contentType->defaultSortOrder;
        }

        $locations = array($locationCreateStruct);

        // BC
        $other_locations = isset($this->dsl['other_parent_locations']) ? $this->dsl['other_parent_locations'] : (
            isset($this->dsl['other_locations']) ? $this->dsl['other_locations'] : null
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

        if (isset($this->dsl['object_states'])) {
            $this->setObjectStates($content, $this->dsl['object_states']);
        }

        // 2nd part of the hack: re-set the content owner to its intended value
        if (isset($this->dsl['version_creator']) || isset($this->dsl['publication_date'])) {
            $contentMetaDataUpdateStruct = $contentService->newContentMetadataUpdateStruct();

            if (isset($this->dsl['version_creator'])) {
                $contentMetaDataUpdateStruct->ownerId = $realContentOwnerId;
            }
            if (isset($this->dsl['publication_date'])) {
                $contentMetaDataUpdateStruct->publishedDate = $this->toDateTime($this->dsl['publication_date']);
            }

            $contentService->updateContentMetadata($content->contentInfo, $contentMetaDataUpdateStruct);
        }

        $this->setReferences($content);

        return $content;
    }

    /**
     * Handle the content update migration action type
     *
     * @todo handle updating of more metadata fields
     */
    protected function update()
    {
        $contentService = $this->repository->getContentService();
        $contentTypeService = $this->repository->getContentTypeService();

        $contentCollection = $this->matchContents('update');

        if (count($contentCollection) > 1 && isset($this->dsl['references'])) {
            throw new \Exception("Can not execute Content update because multiple contents match, and a references section is specified in the dsl. References can be set when only 1 content matches");
        }

        $contentType = null;

        foreach ($contentCollection as $key => $content) {
            $contentInfo = $content->contentInfo;

            if ($contentType == null) {
                $contentType = $contentTypeService->loadContentType($contentInfo->contentTypeId);
            }

            $contentUpdateStruct = $contentService->newContentUpdateStruct();

            if (isset($this->dsl['attributes'])) {
                $this->setFields($contentUpdateStruct, $this->dsl['attributes'], $contentType);
            }

            $versionCreator = null;
            if (isset($this->dsl['version_creator'])) {
                $versionCreator = $this->getUser($this->dsl['version_creator']);
            }

            $draft = $contentService->createContentDraft($contentInfo, null, $versionCreator);
            $contentService->updateContent($draft->versionInfo, $contentUpdateStruct);
            $content = $contentService->publishVersion($draft->versionInfo);

            if (isset($this->dsl['always_available']) ||
                isset($this->dsl['new_remote_id']) ||
                isset($this->dsl['owner']) ||
                isset($this->dsl['modification_date']) ||
                isset($this->dsl['publication_date'])) {

                $contentMetaDataUpdateStruct = $contentService->newContentMetadataUpdateStruct();

                if (isset($this->dsl['always_available'])) {
                    $contentMetaDataUpdateStruct->alwaysAvailable = $this->dsl['always_available'];
                }

                if (isset($this->dsl['new_remote_id'])) {
                    $contentMetaDataUpdateStruct->remoteId = $this->dsl['new_remote_id'];
                }

                if (isset($this->dsl['owner'])) {
                    $owner = $this->getUser($this->dsl['owner']);
                    $contentMetaDataUpdateStruct->ownerId = $owner->id;
                }

                if (isset($this->dsl['modification_date'])) {
                    $contentMetaDataUpdateStruct->modificationDate = $this->toDateTime($this->dsl['modification_date']);
                }

                if (isset($this->dsl['publication_date'])) {
                    $contentMetaDataUpdateStruct->publishedDate = $this->toDateTime($this->dsl['publication_date']);
                }

                $content = $contentService->updateContentMetadata($content->contentInfo, $contentMetaDataUpdateStruct);
            }

            if (isset($this->dsl['section'])) {
                $this->setSection($content, $this->dsl['section']);
            }

            if (isset($this->dsl['object_states'])) {
                $this->setObjectStates($content, $this->dsl['object_states']);
            }

            $contentCollection[$key] = $content;
        }

        $this->setReferences($contentCollection);

        return $contentCollection;
    }

    /**
     * Handle the content delete migration action type
     */
    protected function delete()
    {
        $contentService = $this->repository->getContentService();

        $contentCollection = $this->matchContents('delete');

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
    protected function matchContents($action)
    {
        if (!isset($this->dsl['object_id']) && !isset($this->dsl['remote_id']) && !isset($this->dsl['match'])) {
            throw new \Exception("The ID or remote ID of an object or a Match Condition is required to $action a new location.");
        }

        // Backwards compat
        if (!isset($this->dsl['match'])) {
            if (isset($this->dsl['object_id'])) {
                $this->dsl['match'] = array('content_id' => $this->dsl['object_id']);
            } elseif (isset($this->dsl['remote_id'])) {
                $this->dsl['match'] = array('content_remote_id' => $this->dsl['remote_id']);
            }
        }

        $match = $this->dsl['match'];

        // convert the references passed in the match
        foreach ($match as $condition => $values) {
            if (is_array($values)) {
                foreach ($values as $position => $value) {
                    $match[$condition][$position] = $this->referenceResolver->resolveReference($value);
                }
            } else {
                $match[$condition] = $this->referenceResolver->resolveReference($values);
            }
        }

        return $this->contentMatcher->match($match);
    }

    /**
     * Helper function to set the fields of a ContentCreateStruct based on the DSL attribute settings.
     *
     * @param ContentCreateStruct|ContentUpdateStruct $createOrUpdateStruct
     * @param ContentType $contentType
     * @param array $fields see description of expected format in code below
     * @throws \Exception
     */
    protected function setFields($createOrUpdateStruct, array $fields, ContentType $contentType)
    {
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
            } else {
                $fieldIdentifier = $key;
                $fieldValue = $field;
            }

            if (!isset($contentType->fieldDefinitionsByIdentifier[$fieldIdentifier])) {
                throw new \Exception("Field '$fieldIdentifier' is not present in field type '{$contentType->identifier}'");
            }

            $fieldDefinition = $contentType->fieldDefinitionsByIdentifier[$fieldIdentifier];
            $fieldValue = $this->getFieldValue($fieldValue, $fieldDefinition, $contentType->identifier, $this->context);

            $createOrUpdateStruct->setField($fieldIdentifier, $fieldValue, $this->getLanguageCode());

            $i++;
        }
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

    /**
     * Create the field value for either a primitive (ie. scalar) or complex field
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
        if (is_array($value) || $this->complexFieldManager->managesField($fieldTypeIdentifier, $contentTypeIdentifier)) {
            return $this->complexFieldManager->hashToFieldValue($fieldTypeIdentifier, $contentTypeIdentifier, $value, $context);
        }

        return $this->getSingleFieldValue($value, $fieldDefinition, $contentTypeIdentifier, $context);
    }

    /**
     * Create the field value for a primitive field
     * This function is needed to get past validation on Checkbox fieldtype (eZP bug)
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
     * Sets references to certain content attributes.
     * The Content Manager currently supports setting references to object_id and location_id
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Content|ContentCollection $content
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute
     * @return boolean
     *
     * @todo add support for other attributes: remote ids, contentTypeId, contentTypeIdentifier, section, etc...
     */
    protected function setReferences($content)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        if ($content instanceof ContentCollection) {
            if (count($content) > 1) {
                throw new \InvalidArgumentException('Content Manager does not support setting references for creating/updating of multiple contents');
            }
            $content = reset($content);
        }

        foreach ($this->dsl['references'] as $reference) {

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
                case 'location_id':
                    $value = $content->contentInfo->mainLocationId;
                    break;
                case 'path':
                    $locationService = $this->repository->getLocationService();
                    $value = $locationService->loadLocation($content->contentInfo->mainLocationId)->pathString;
                    break;
                default:
                    throw new \InvalidArgumentException('Content Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
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

    /**
     * @param array $matchCondition
     * @param string $mode
     * @throws \Exception
     * @return array
     *
     * @todo add support for dumping all object languages
     */
    public function generateMigration(array $matchCondition, $mode)
    {
        $previousUserId = $this->loginUser(self::ADMIN_USER_ID);
        $contentCollection = $this->contentMatcher->match($matchCondition);
        $data = array();

        /** @var \eZ\Publish\API\Repository\Values\Content\Content $content */
        foreach ($contentCollection as $content) {

            $location = $this->repository->getLocationService()->loadLocation($content->contentInfo->mainLocationId);
            $contentType = $this->repository->getContentTypeService()->loadContentType(
                $content->contentInfo->contentTypeId
            );
            $fieldTypeService = $this->repository->getFieldTypeService();

            $contentData = array(
                'type' => 'content',
                'mode' => $mode
            );

            switch ($mode) {
                case 'create':
                    // @todo Add sort_field and sort_order
                    // @todo Add 2ndary locations
                    $contentData = array_merge(
                        $contentData,
                        array(
                            'content_type' => $contentType->identifier,
                            'parent_location' => $location->parentLocationId,
                            'priority' => $location->priority,
                            'is_hidden' => $location->invisible,
                            'remote_id' => $content->contentInfo->remoteId,
                            'location_remote_id' => $location->remoteId
                        )
                    );
                    break;
                case 'update':
                case 'delete':
                    $contentData = array_merge(
                        $contentData,
                        array(
                            'match' => array(
                                'content_remote_id' => $content->contentInfo->remoteId
                            )
                        )
                    );
                    break;
                default:
                    throw new \Exception("Executor 'content' doesn't support mode '$mode'");
            }

            if ($mode != 'delete') {
                $attributes = array();
                foreach ($content->getFieldsByLanguage($this->getLanguageCode()) as $fieldIdentifier => $field) {
                    $fieldDefinition = $contentType->getFieldDefinition($fieldIdentifier);
                    $attributes[$field->fieldDefIdentifier] = $this->complexFieldManager->fieldValueToHash(
                        $fieldDefinition->fieldTypeIdentifier, $contentType->identifier, $field->value
                    );
                }

                $contentData = array_merge(
                    $contentData,
                    array(
                        'lang' => $this->getLanguageCode(),
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

        $this->loginUser($previousUserId);
        return $data;
    }
}

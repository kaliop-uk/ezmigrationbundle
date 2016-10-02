<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\ContentType\FieldDefinition;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\ContentCreateStruct;
use eZ\Publish\API\Repository\Values\Content\ContentUpdateStruct;
use eZ\Publish\Core\FieldType\Checkbox\Value as CheckboxValue;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;
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
class ContentManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('content');

    protected $contentMatcher;
    protected $sectionMatcher;
    protected $userMatcher;
    protected $objectStateMatcher;
    protected $complexFieldManager;
    protected $locationManager;

    public function __construct(ContentMatcher $contentMatcher, SectionMatcher $sectionMatcher, UserMatcher $userMatcher,
        ObjectStateMatcher $objectStateMatcher, $complexFieldManager, $locationManager)
    {
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

        $contentTypeIdentifier = $this->dsl['content_type'];
        $contentTypeIdentifier = $this->resolveReferences($contentTypeIdentifier);
        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);

        $contentCreateStruct = $contentService->newContentCreateStruct($contentType, $this->getLanguageCode());

        $this->setFields($contentCreateStruct, $this->dsl['attributes'], $contentType);

        if (isset($this->dsl['remote_id'])) {
            $contentCreateStruct->remoteId = $this->dsl['remote_id'];
        }

        if (isset($this->dsl['section'])) {
            $sectionId = $this->dsl['section'];
            $sectionId = $this->resolveReferences($sectionId);
            $contentCreateStruct->sectionId = $sectionId;
        }

        if (isset($this->dsl['owner'])) {
            $owner = $this->getUser($this->dsl['owner']);
            $contentCreateStruct->ownerId = $owner->id;
        }

        if (isset($this->dsl['modification_date'])) {
            $contentCreateStruct->modificationDate = new \DateTime($this->dsl['modification_date']);
        }

        // instantiate a location create struct from the parent location:
        $locationId = $this->dsl['main_location'];
        // 1st resolve references
        $locationId = $this->resolveReferences($locationId);
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

        if (isset($this->dsl['sort_order'])) {
            $locationCreateStruct->sortOrder = $this->locationManager->getSortOrder($this->dsl['sort_order']);
        }

        if (isset($this->dsl['sort_field'])) {
            $locationCreateStruct->sortField = $this->locationManager->getSortField($this->dsl['sort_field']);
        }

        $locations = array($locationCreateStruct);

        if (isset($this->dsl['other_locations'])) {
            foreach ($this->dsl['other_locations'] as $locationId) {
                $locationId = $this->resolveReferences($locationId);
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

            $draft = $contentService->createContentDraft($contentInfo);
            $contentService->updateContent($draft->versionInfo,$contentUpdateStruct);
            $content = $contentService->publishVersion($draft->versionInfo);

            if (isset($this->dsl['new_remote_id']) || isset($this->dsl['new_remote_id']) ||
                isset($this->dsl['modification_date']) || isset($this->dsl['publication_date'])) {

                $contentMetaDataUpdateStruct = $contentService->newContentMetadataUpdateStruct();

                if (isset($this->dsl['new_remote_id'])) {
                    $contentMetaDataUpdateStruct->remoteId = $this->dsl['new_remote_id'];
                }

                if (isset($this->dsl['owner'])) {
                    $owner = $this->getUser($this->dsl['owner']);
                    $contentMetaDataUpdateStruct->ownerId = $owner->id;
                }

                if (isset($this->dsl['modification_date'])) {
                    $contentMetaDataUpdateStruct->modificationDate = new \DateTime($this->dsl['modification_date']);
                }

                if (isset($this->dsl['publication_date'])) {
                    $contentMetaDataUpdateStruct->publishedDate = new \DateTime($this->dsl['publication_date']);
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
                    $match[$condition][$position] = $this->resolveReferences($value);
                }
            } else {
                $match[$condition] = $this->resolveReferences($values);
            }
        }

        return $this->contentMatcher->match($match);
    }

    /**
     * Helper function to set the fields of a ContentCreateStruct based on the DSL attribute settings.
     *
     * @param ContentCreateStruct|ContentUpdateStruct $createOrUpdateStruct
     * @param ContentType $contentType
     * @param array $fields
     * @throws \Exception
     */
    protected function setFields($createOrUpdateStruct, array $fields, ContentType $contentType)
    {
        foreach ($fields as $field) {
            // each $field is one key value pair
            // eg.: $field = array($fieldIdentifier => $fieldValue)
            $fieldIdentifier = key($field);

            $fieldType = $contentType->fieldDefinitionsByIdentifier[$fieldIdentifier];
            if ($fieldType == null) {
                throw new \Exception("Field '$fieldIdentifier' is not present in field type '{$contentType->identifier}'");
            }

            if (is_array($field[$fieldIdentifier])) {
                // Complex field might need special handling eg.: ezimage, ezbinaryfile
                $fieldValue = $this->getComplexFieldValue($field[$fieldIdentifier], $fieldType, $this->context);
            } else {
                // Primitive field eg.: ezstring, ezxml etc.
                $fieldValue = $this->getSingleFieldValue($field[$fieldIdentifier], $fieldType, $this->context);
            }

            $createOrUpdateStruct->setField($fieldIdentifier, $fieldValue, $this->getLanguageCode());
        }
    }

    protected function setSection(Content $content, $sectionKey)
    {
        $sectionKey = $this->resolveReferences($sectionKey);
        $section = $this->sectionMatcher->matchByKey($sectionKey);

        $sectionService = $this->repository->getSectionService();
        $sectionService->assignSection($content->contentInfo, $section);
    }

    protected function setObjectStates(Content $content, array $stateKeys)
    {
        foreach($stateKeys as $stateKey) {
            $stateKey = $this->resolveReferences($stateKey);
            /** @var \eZ\Publish\API\Repository\Values\ObjectState\ObjectState $state */
            $state = $this->objectStateMatcher->matchByKey($stateKey);

            $stateService = $this->repository->getObjectStateService();
            $stateService->setContentState($content->contentInfo, $state->getObjectStateGroup(), $state);
        }
    }

    /**
     * Create the field value for a primitive field
     * This function is needed to get past validation on Checkbox fieldtype (eZP bug)
     *
     * @param mixed $value
     * @param FieldDefinition $fieldDefinition
     * @param array $context
     * @throws \InvalidArgumentException
     * @return object
     */
    protected function getSingleFieldValue($value, FieldDefinition $fieldDefinition, array $context = array())
    {
        switch ($fieldDefinition->fieldTypeIdentifier) {
            case 'ezboolean':
                $value = new CheckboxValue(($value == 1) ? true : false);
                break;
            default:
                // do nothing
        }

        // q: do we really want this ?
        $value = $this->resolveReferences($value);

        return $value;
    }

    /**
     * Create the field value for a complex field eg.: ezimage, ezfile
     *
     * @param FieldDefinition $fieldDefinition
     * @param array $fieldValueArray
     * @param array $context
     * @return object
     */
    protected function getComplexFieldValue(array $fieldValueArray, FieldDefinition $fieldDefinition, array $context = array())
    {
        return $this->complexFieldManager->getComplexFieldValue($fieldDefinition->fieldTypeIdentifier, $fieldValueArray, $context);
    }

    /**
     * Load user using either login, email, id - resolving eventual references
     * @param int|string $userKey
     * @return \eZ\Publish\API\Repository\Values\User\User
     */
    protected function getUser($userKey)
    {
        $userKey = $this->resolveReferences($userKey);
        return $this->userMatcher->matchByKey($userKey);
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
}

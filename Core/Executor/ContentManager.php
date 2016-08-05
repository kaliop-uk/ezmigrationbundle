<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\Content\ContentCreateStruct;
use eZ\Publish\API\Repository\Values\Content\ContentUpdateStruct;
use eZ\Publish\Core\FieldType\Checkbox\Value as CheckboxValue;

/**
 * Implements the actions for managing (create/update/delete) Content in the system through
 * migrations and abstracts away the eZ Publish Public API.
 *
 * @todo add support for updating of content metadata
 */
class ContentManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('content');

    protected $complexFieldManager;

    public function __construct($complexFieldManager)
    {
        $this->complexFieldManager = $complexFieldManager;
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
                    if ($this->referenceResolver->isReference($value)) {
                        $match[$condition][$position] = $this->referenceResolver->getReferenceValue($value);
                    }
                }
            } else {
                if ($this->referenceResolver->isReference($values)) {
                    $match[$condition] = $this->referenceResolver->getReferenceValue($values);
                }
            }
        }

        return $this->contentMatcher->matchContent($match);
    }

    /**
     * Handle the content create migration action type
     */
    protected function create()
    {
        $this->loginUser();

        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();

        $contentTypeIdentifier = $this->dsl['content_type'];
        if ($this->referenceResolver->isReference($contentTypeIdentifier)) {
            $contentTypeIdentifier = $this->referenceResolver->getReferenceValue($contentTypeIdentifier);
        }
        $contentType = $contentTypeService->loadContentTypeByIdentifier($contentTypeIdentifier);

        // FIXME: Defaulting in language code for now
        $contentCreateStruct = $contentService->newContentCreateStruct($contentType, self::DEFAULT_LANGUAGE_CODE);
        $this->setFields($contentCreateStruct, $this->dsl['attributes']);

        if (array_key_exists('remote_id', $this->dsl)) {
            $contentCreateStruct->remoteId = $this->dsl['remote_id'];
        }

        // instantiate a location create struct from the parent location
        $locationId = $this->dsl['main_location'];
        if ($this->referenceResolver->isReference($locationId)) {
            $locationId = $this->referenceResolver->getReferenceValue($locationId);
        }
        $locationCreateStruct = $locationService->newLocationCreateStruct($locationId);
        if (array_key_exists('remote_id', $this->dsl)) {
            $locationCreateStruct->remoteId = $this->dsl['remote_id'] . '_location';
        }

        if (array_key_exists('priority', $this->dsl)) {
            $locationCreateStruct->priority = $this->dsl['priority'];
        }

        $locations = array($locationCreateStruct);

        if (array_key_exists('other_locations', $this->dsl)) {
            foreach ($this->dsl['other_locations'] as $otherLocation) {
                $locationId = $otherLocation;
                if ($this->referenceResolver->isReference($locationId)) {
                    $locationId = $this->referenceResolver->getReferenceValue($otherLocation);
                }
                $secondaryLocationCreateStruct = $locationService->newLocationCreateStruct($locationId);
                array_push($locations, $secondaryLocationCreateStruct);
            }
        }

        // create a draft using the content and location create struct and publish it
        $draft = $contentService->createContent($contentCreateStruct, $locations);
        $content = $contentService->publishVersion($draft->versionInfo);

        $this->setReferences($content);
    }

    /**
     * Handle the content update migration action type
     */
    protected function update()
    {
        $this->loginUser();

        $contentService = $this->repository->getContentService();
        $contentTypeService = $this->repository->getContentTypeService();

        /*if (isset($this->dsl['object_id'])) {
            $objectId = $this->dsl['object_id'];
            if ($this->referenceResolver->isReference($objectId)) {
                $objectId = $this->referenceResolver->getReferenceValue($objectId);
            }
            $contentToUpdate = $contentService->loadContent($objectId);
            $contentInfo = $contentToUpdate->contentInfo;
        } else {
            $remoteId = $this->dsl['remote_id'];
            if ($this->referenceResolver->isReference($remoteId)) {
                $remoteId = $this->referenceResolver->getReferenceValue($remoteId);
            }

            //try {
                $contentInfo = $contentService->loadContentInfoByRemoteId($remoteId);
            // disabled in v2: we disallow this. For matching location-remote-id, use the 'match' keyword
            //} catch (\eZ\Publish\API\Repository\Exceptions\NotFoundException $e) {
            //    $location = $this->repository->getLocationService()->loadLocationByRemoteId($remoteId);
            //    $contentInfo = $location->contentInfo;
            //}
        }*/

        $contentCollection = $this->matchContents('update');

        if (count($contentCollection) > 1 && array_key_exists('references', $this->dsl)) {
            throw new \Exception("Can not execute Content update because multiple contents match, and a references section is specified in the dsl. References can be set when only 1 content matches");
        }

        $contentType = null;

        foreach ($contentCollection as $content) {
            $contentInfo = $content->contentInfo;

            if ($contentType == null) {
                $contentTypeService->loadContentType($contentInfo->contentTypeId);
            }

            $contentUpdateStruct = $contentService->newContentUpdateStruct();

            if (array_key_exists('attributes', $this->dsl)) {
                $this->setFieldsToUpdate($contentUpdateStruct, $this->dsl['attributes'], $contentType);
            }

            $draft = $contentService->createContentDraft($contentInfo);
            $contentService->updateContent($draft->versionInfo,$contentUpdateStruct);
            $content = $contentService->publishVersion($draft->versionInfo);

            if (array_key_exists('new_remote_id', $this->dsl)) {
                // Update object remote ID
                $contentMetaDataUpdateStruct = $contentService->newContentMetadataUpdateStruct();
                $contentMetaDataUpdateStruct->remoteId = $this->dsl['new_remote_id'];
                $content = $contentService->updateContentMetadata($content->contentInfo, $contentMetaDataUpdateStruct);

                // Update main location remote ID
                // removed in v2: this is NOT generic!
                //$locationService = $this->repository->getLocationService();
                //$locationUpdateStruct = $locationService->newLocationUpdateStruct();
                //$locationUpdateStruct->remoteId = $this->dsl['new_remote_id'] . '_location';
                //$location = $locationService->loadLocation($content->contentInfo->mainLocationId);
                //$locationService->updateLocation($location, $locationUpdateStruct);
            }

            $this->setReferences($contentCollection);
        }
    }

    /**
     * Handle the content delete migration action type
     */
    protected function delete()
    {
        $this->loginUser();

        $contentService = $this->repository->getContentService();

        $contentCollection = $this->matchContents('delete');

        foreach ($contentCollection as $content) {
            $contentService->deleteContent($content->contentInfo);
        }
    }

    /**
     * Helper function to set the fields of a ContentCreateStruct based on the DSL attribute settings.
     *
     * @param ContentCreateStruct $createStruct
     * @param array $fields
     */
    protected function setFields(ContentCreateStruct &$createStruct, array $fields)
    {
        foreach ($fields as $field) {
            // each $field is one key value pair
            // eg.: $field = array($fieldIdentifier => $fieldValue)
            $fieldIdentifier = key($field);

            $fieldTypeIdentifier = $createStruct->contentType->fieldDefinitionsByIdentifier[$fieldIdentifier]->fieldTypeIdentifier;

            if (is_array($field[$fieldIdentifier])) {
                // Complex field needs special handling eg.: ezimage, ezbinaryfile
                $fieldValue = $this->handleComplexField($fieldTypeIdentifier, $field[$fieldIdentifier]);
            } else {
                // Primitive field eg.: ezstring, ezxml etc.
                $fieldValue = $this->handleSingleField($fieldTypeIdentifier, $fieldIdentifier, $field[$fieldIdentifier]);
            }

            $createStruct->setField($fieldIdentifier, $fieldValue, self::DEFAULT_LANGUAGE_CODE);
        }
    }

    /**
     * Helper function to set the fields of a ContentUpdateStruct based on the DSL attribute settings.
     *
     * @param ContentUpdateStruct $updateStruct
     * @param array $fields
     */
    protected function setFieldsToUpdate(ContentUpdateStruct &$updateStruct, array $fields, ContentType $contentType)
    {
        foreach ($fields as $field) {
            // each $field is one key value pair
            // eg.: $field = array($fieldIdentifier => $fieldValue)
            $fieldIdentifier = key($field);

            $fieldTypeIdentifier = $contentType->fieldDefinitionsByIdentifier[$fieldIdentifier]->fieldTypeIdentifier;

            if (is_array($field[$fieldIdentifier])) {
                // Complex field needs special handling eg.: ezimage, ezbinaryfile
                $fieldValue = $this->handleComplexField($fieldTypeIdentifier, $field[$fieldIdentifier]);
            } else {
                // Primitive field eg.: ezstring, ezxml etc.
                $fieldValue = $this->handleSingleField($fieldTypeIdentifier, $fieldIdentifier, $field[$fieldIdentifier]);
            }

            $updateStruct->setField($fieldIdentifier, $fieldValue, self::DEFAULT_LANGUAGE_CODE);
        }
    }

    /**
     * Create the field value for a primitive field
     * This function is needed to get past validation on Checkbox fieldtype (eZP bug)
     *
     * @param string $fieldTypeIdentifier
     * @param string $identifier
     * @param mixed $value
     * @throws \InvalidArgumentException
     * @return object
     */
    protected function handleSingleField($fieldTypeIdentifier, $identifier, $value)
    {
        switch ($fieldTypeIdentifier) {
            case 'ezboolean':
                $fieldValue = new CheckboxValue(($value == 1) ? true : false);
                break;
            default:
                $fieldValue = $value;
        }

        if ($this->referenceResolver->isReference($value)) {
            $fieldValue = $this->referenceResolver->getReferenceValue($value);
        }

        return $fieldValue;
    }

    /**
     * Create the field value for a complex field eg.: ezimage, ezfile
     *
     * @param string $fieldTypeIdentifier
     * @param array $fieldValueArray
     * @param array $context
     * @return object
     */
    protected function handleComplexField($fieldTypeIdentifier, array $fieldValueArray, array $context = array())
    {
        return $this->complexFieldManager->getComplexFieldValue($fieldTypeIdentifier, $fieldValueArray, $context);
    }

    /**
     * Sets references to certain content attributes.
     * The Content Manager currently supports setting references to object_id and location_id
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Content $content
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute
     * @return boolean
     *
     * @todo add support for other attributes: contentTypeId, contentTypeIdentifier, section, etc...
     */
    protected function setReferences($content)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        foreach ($this->dsl['references'] as $reference) {

            switch ($reference['attribute']) {
                case 'object_id':
                case 'id':
                    $value = $content->id;
                    break;
                case 'location_id':
                    $value = $content->contentInfo->mainLocationId;
                    break;
                default:
                    throw new \InvalidArgumentException('Content Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\API\Managers;

use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use Kaliop\eZMigrationBundle\Core\API\Managers\AbstractManager;
use eZ\Publish\API\Repository\Values\Content\ContentCreateStruct;
use eZ\Publish\API\Repository\Values\Content\ContentUpdateStruct;
use Kaliop\eZMigrationBundle\Core\API\ReferenceHandler;
use eZ\Publish\Core\FieldType\Image\Value as ImageValue;
use eZ\Publish\Core\FieldType\BinaryFile\Value as BinaryFileValue;
use eZ\Publish\Core\FieldType\Checkbox\Value as CheckboxValue;

/**
 * Class ContentManager
 *
 * Class implementing the actions for managing (create/update/delete) Content in the system through
 * migrations and abstracts away the eZ Publish Public API.
 *
 * @package Kaliop\eZMigrationBundle\Core\API\Managers
 *
 * @todo add support for updating of content metadata
 */
class ContentManager extends AbstractManager
{

    /**
     * Handle the content create migration action type
     */
    public function create()
    {
        $contentService = $this->repository->getContentService();
        $locationService = $this->repository->getLocationService();
        $contentTypeService = $this->repository->getContentTypeService();

        // Authenticate the user
        $this->loginUser();

        $contentTypeIdentifier = $this->dsl['content_type'];
        if ($this->isReference($contentTypeIdentifier)) {
            $contentTypeIdentifier = $this->getReference($contentTypeIdentifier);
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
        if ($this->isReference($locationId)) {
            $locationId = $this->getReference($locationId);
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
                if ($this->isReference($locationId)) {
                    $locationId = $this->getReference($otherLocation);
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
    public function update()
    {
        $contentService = $this->repository->getContentService();
        $contentTypeService = $this->repository->getContentTypeService();

        $this->loginUser();

        if (isset($this->dsl['object_id'])) {
            $objectId = $this->dsl['object_id'];
            if ($this->isReference($objectId)) {
                $objectId = $this->getReference($objectId);
            }
            $contentToUpdate = $contentService->loadContent($objectId);
            $contentInfo = $contentToUpdate->contentInfo;
        } else {
            $remoteId = $this->dsl['remote_id'];
            if ($this->isReference($remoteId)) {
                $remoteId = $this->getReference($remoteId);
            }

            try {
                $contentInfo = $contentService->loadContentInfoByRemoteId($remoteId);
            } catch (\eZ\Publish\API\Repository\Exceptions\NotFoundException $e) {
                $location = $this->repository->getLocationService()->loadLocationByRemoteId($remoteId);
                $contentInfo = $location->contentInfo;
            }
        }

        $contentType = $contentTypeService->loadContentType($contentInfo->contentTypeId);
        $contentUpdateStruct = $contentService->newContentUpdateStruct();

        if (array_key_exists('attributes', $this)) {
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
            $locationService = $this->repository->getLocationService();
            $locationUpdateStruct = $locationService->newLocationUpdateStruct();
            $locationUpdateStruct->remoteId = $this->dsl['new_remote_id'] . '_location';
            $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
            $locationService->updateLocation($location, $locationUpdateStruct);
        }

        $this->setReferences($content);
    }

    /**
     * Handle the content delete migration action type
     */
    public function delete()
    {
        if (!isset($this->dsl['object_id']) && !isset($this->dsl['remote_id'])) {
            throw new \Exception('No object identifier provided for deletion');
        }

        $this->loginUser();

        $contentService = $this->repository->getContentService();

        if(isset($this->dsl['object_id'])){
            if (!is_array($this->dsl['object_id'])) {
                $this->dsl['object_id'] = array($this->dsl['object_id']);
            }

            foreach ($this->dsl['object_id'] as $objectId) {

                $content = $contentService->loadContent($objectId);
                $contentService->deleteContent($content->contentInfo);
            }
        } else if(isset($this->dsl['remote_id'])) {
            if (!is_array($this->dsl['remote_id'])) {
                $this->dsl['remote_id'] = array($this->dsl['remote_id']);
            }

            foreach ($this->dsl['remote_id'] as $objectId) {
                $content = $contentService->loadContentByRemoteId($objectId);
                $contentService->deleteContent($content->contentInfo);
            }
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
            // eg.: $field = array( $fieldIdentifier => $fieldValue )
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
            // eg.: $field = array( $fieldIdentifier => $fieldValue )
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
                $fieldValue = new CheckboxValue( ($value == 1) ? true : false );
                break;
            default:
                $fieldValue = $value;
        }

        if ($this->isReference($value)) {
            $fieldValue = $this->getReference($value);
        }

        return $fieldValue;
    }

    /**
     * Create the field value for a complex field eg.: ezimage, ezfile
     *
     * @param array $fieldValueArray
     * @throws \InvalidArgumentException
     * @return object
     */
    protected function handleComplexField($fieldTypeIdentifier, array $fieldValueArray)
    {
        $fieldManager = $this->container->get('ez_migration_bundle.complex.field.manager');
        $fieldManager->setBundle($this->bundle);

        $complexField = $fieldManager->getComplexField($fieldTypeIdentifier, $fieldValueArray, $this);

        return $complexField->createValue();

//        switch ($fieldValueArray['type']) {
//            case 'ezimage':
//                $fieldValue = $this->createImageValue($fieldValueArray);
//                break;
//            case 'ezbinaryfile':
//                $fieldValue = $this->createBinaryFileValue($fieldValueArray);
//                break;
//            case 'ezxmltext':
//                $fieldValue = $this->parseXMLTextForReferences($fieldValueArray);
//                break;
//            default:
//                throw new \InvalidArgumentException('Content manager does not support complex field type: ' . $fieldValueArray['type']);
//        }
//
//        return $fieldValue;
    }

    /**
     * Sets references to certain attributes.
     *
     * The Content Manager currently supports setting references to object_id and location_id
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Content $content
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute
     * @return boolean
     */
    protected function setReferences($content)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        $referenceHandler = ReferenceHandler::instance();

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

            $referenceHandler->addReference($reference['identifier'], $value);
        }

        return true;
    }
}

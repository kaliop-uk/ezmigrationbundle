<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\Content\Location;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;
use Kaliop\eZMigrationBundle\API\Collection\LocationCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\LocationMatcher;
use Kaliop\eZMigrationBundle\Core\Helper\SortConverter;

/**
 * Handles location migrations.
 */
class LocationManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('location');
    protected $supportedActions = array('create', 'load', 'update', 'delete', 'trash');

    protected $contentMatcher;
    protected $locationMatcher;
    protected $sortConverter;

    public function __construct(ContentMatcher $contentMatcher, LocationMatcher $locationMatcher, SortConverter $sortConverter)
    {
        $this->contentMatcher = $contentMatcher;
        $this->locationMatcher = $locationMatcher;
        $this->sortConverter = $sortConverter;
    }

    /**
     * Method to handle the create operation of the migration instructions
     */
    protected function create($step)
    {
        $locationService = $this->repository->getLocationService();

        if (!isset($step->dsl['parent_location']) && !isset($step->dsl['parent_location_id'])) {
            throw new InvalidStepDefinitionException('Missing parent location id. This is required to create the new location.');
        }

        // support legacy tag: parent_location_id
        if (!isset($step->dsl['parent_location']) && isset($step->dsl['parent_location_id'])) {
            $parentLocationIds = $step->dsl['parent_location_id'];
        } else {
            $parentLocationIds = $step->dsl['parent_location'];
        }

        if (!is_array($parentLocationIds)) {
            $parentLocationIds = array($parentLocationIds);
        }

        if (isset($step->dsl['is_main']) && count($parentLocationIds) > 1) {
            throw new InvalidStepDefinitionException('Can not set more than one new location as main.');
        }

        // resolve references and remote ids
        foreach ($parentLocationIds as $id => $parentLocationId) {
            $parentLocationId = $this->referenceResolver->resolveReference($parentLocationId);
            $parentLocationIds[$id] = $this->matchLocationByKey($parentLocationId)->id;
        }

        $contentCollection = $this->matchContents('create', $step);

        $locations = array();
        foreach ($contentCollection as $content) {
            $contentInfo = $content->contentInfo;

            foreach ($parentLocationIds as $parentLocationId) {
                $locationCreateStruct = $locationService->newLocationCreateStruct($parentLocationId);

                if (isset($step->dsl['is_hidden'])) {
                    $locationCreateStruct->hidden = $this->referenceResolver->resolveReference($step->dsl['is_hidden']);
                }

                if (isset($step->dsl['priority'])) {
                    $locationCreateStruct->priority = $this->referenceResolver->resolveReference($step->dsl['priority']);
                }

                if (isset($step->dsl['sort_order'])) {
                    $locationCreateStruct->sortOrder = $this->getSortOrder($this->referenceResolver->resolveReference($step->dsl['sort_order']));
                }

                if (isset($step->dsl['sort_field'])) {
                    $locationCreateStruct->sortField = $this->getSortField($this->referenceResolver->resolveReference($step->dsl['sort_field']));
                }

                if (isset($step->dsl['remote_id'])) {
                    $locationCreateStruct->remoteId = $this->referenceResolver->resolveReference($step->dsl['remote_id']);
                }

                $location = $locationService->createLocation($contentInfo, $locationCreateStruct);

                if (isset($step->dsl['is_main'])) {
                    $this->setMainLocation($location);
                    // we have to reload the location so that correct data can be set as reference
                    $location = $locationService->loadLocation($location->id);
                }

                $locations[] = $location;
            }
        }

        $locationCollection = new LocationCollection($locations);

        $this->validateResultsCount($locationCollection, $step);

        $this->setReferences($locationCollection, $step);

        return $locationCollection;
    }

    protected function load($step)
    {
        $locationCollection = $this->matchLocations('load', $step);

        $this->validateResultsCount($locationCollection, $step);

        $this->setReferences($locationCollection, $step);

        return $locationCollection;
    }

    /**
     * Updates information for a location like priority, sort field and sort order.
     * Updates the visibility of the location when needed.
     * Can move a location and its children to a new parent location or swap two locations.
     */
    protected function update($step)
    {
        $locationService = $this->repository->getLocationService();

        $locationCollection = $this->matchLocations('update', $step);

        $this->validateResultsCount($locationCollection, $step);

        if (count($locationCollection) > 1 && isset($step->dsl['swap_with_location'])) {
            throw new \Exception("Can not execute Location update because multiple locations match, and a swap_with_location is specified in the dsl.");
        }

        // support legacy tag: parent_location_id
        if (isset($step->dsl['swap_with_location']) && (isset($step->dsl['parent_location']) || isset($step->dsl['parent_location_id']))) {
            throw new InvalidStepDefinitionException('Cannot move location to a new parent and swap location with another location at the same time.');
        }

        foreach ($locationCollection as $key => $location) {

            if (isset($step->dsl['priority'])
                || isset($step->dsl['sort_field'])
                || isset($step->dsl['sort_order'])
                || isset($step->dsl['remote_id'])
            ) {
                $locationUpdateStruct = $locationService->newLocationUpdateStruct();

                if (isset($step->dsl['priority'])) {
                    $locationUpdateStruct->priority = $this->referenceResolver->resolveReference($step->dsl['priority']);
                }

                if (isset($step->dsl['sort_field'])) {
                    $locationUpdateStruct->sortField = $this->getSortField($this->referenceResolver->resolveReference($step->dsl['sort_field']), $location->sortField);
                }

                if (isset($step->dsl['sort_order'])) {
                    $locationUpdateStruct->sortOrder = $this->getSortOrder($this->referenceResolver->resolveReference($step->dsl['sort_order']), $location->sortOrder);
                }

                if (isset($step->dsl['remote_id'])) {
                    $locationUpdateStruct->remoteId = $this->referenceResolver->resolveReference($step->dsl['remote_id']);
                }

                $location = $locationService->updateLocation($location, $locationUpdateStruct);
            }

            // Check if visibility needs to be updated
            if (isset($step->dsl['is_hidden'])) {
                if ($step->dsl['is_hidden']) {
                    $location = $locationService->hideLocation($location);
                } else {
                    $location = $locationService->unhideLocation($location);
                }
            }

            // Move or swap location
            if (isset($step->dsl['parent_location']) || isset($step->dsl['parent_location_id'])) {
                // Move the location and all its children to a new parent
                $parentLocationId = isset($step->dsl['parent_location']) ? $step->dsl['parent_location'] : $step->dsl['parent_location_id'];
                $parentLocationId = $this->referenceResolver->resolveReference($parentLocationId);

                $newParentLocation = $this->matchLocationByKey($parentLocationId);

                $locationService->moveSubtree($location, $newParentLocation);

                // we have to reload the location to be able to set references to the modified data
                $location = $locationService->loadLocation($location->id);
            } elseif (isset($step->dsl['swap_with_location'])) {
                // Swap locations
                $swapLocationId = $step->dsl['swap_with_location'];
                $swapLocationId = $this->referenceResolver->resolveReference($swapLocationId);

                $locationToSwap = $this->matchLocationByKey($swapLocationId);

                $locationService->swapLocation($location, $locationToSwap);

                // we have to reload the location to be able to set references to the modified data
                $location = $locationService->loadLocation($location->id);
            }

            // make the location the main one
            if (isset($step->dsl['is_main'])) {
                $this->setMainLocation($location);

                //have to reload the location so that correct data can be set as reference
                $location = $locationService->loadLocation($location->id);
            }

            $locationCollection[$key] = $location;
        }

        $this->setReferences($locationCollection, $step);

        return $locationCollection;
    }

    /**
     * Delete locations
     */
    protected function delete($step)
    {
        $locationCollection = $this->matchLocations('delete', $step);

        $this->validateResultsCount($locationCollection, $step);

        $this->setReferences($locationCollection, $step);

        $locationService = $this->repository->getLocationService();

        foreach ($locationCollection as $location) {
            $locationService->deleteLocation($location);
        }

        return $locationCollection;
    }

    /**
     * Delete locations sending them to the trash
     */
    protected function trash($step)
    {
        $locationCollection = $this->matchLocations('delete', $step);

        $this->validateResultsCount($locationCollection, $step);

        $this->setReferences($locationCollection, $step);

        $trashService = $this->repository->getTrashService();

        foreach ($locationCollection as $location) {
            $trashService->trash($location);
        }

        return $locationCollection;
    }

    /**
     * @param string $action
     * @return LocationCollection
     * @throws \Exception
     */
    protected function matchLocations($action, $step)
    {
        if (!isset($step->dsl['location_id']) && !isset($step->dsl['match'])) {
            throw new InvalidStepDefinitionException("The id or a match condition is required to $action a location");
        }

        // Backwards compat
        if (isset($step->dsl['match'])) {
            $match = $step->dsl['match'];
        } else {
            $match = array('location_id' => $step->dsl['location_id']);
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($match);

        $offset = isset($step->dsl['match_offset']) ? $this->referenceResolver->resolveReference($step->dsl['match_offset']) : 0;
        $limit = isset($step->dsl['match_limit']) ? $this->referenceResolver->resolveReference($step->dsl['match_limit']) : 0;
        $sort = isset($step->dsl['match_sort']) ? $this->referenceResolver->resolveReference($step->dsl['match_sort']) : array();

        $tolerateMisses = isset($step->dsl['match_tolerate_misses']) ? $this->referenceResolver->resolveReference($step->dsl['match_tolerate_misses']) : false;

        return $this->locationMatcher->match($match, $sort, $offset, $limit, $tolerateMisses);
    }

    /**
     * @param Location $location
     * @param array $references the definitions of the references to set
     * @throws InvalidStepDefinitionException
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($location, array $references, $step)
    {
        $refs = array();

        foreach ($references as $key => $reference) {

            $reference = $this->parseReferenceDefinition($key, $reference);

            switch ($reference['attribute']) {
                case 'location_id':
                case 'id':
                    $value = $location->id;
                    break;
                case 'remote_id':
                case 'location_remote_id':
                    $value = $location->remoteId;
                    break;
                case 'always_available':
                    $value = $location->contentInfo->alwaysAvailable;
                    break;
                case 'content_id':
                    $value = $location->contentId;
                    break;
                case 'content_type_id':
                    $value = $location->contentInfo->contentTypeId;
                    break;
                case 'content_type_identifier':
                    $contentTypeService = $this->repository->getContentTypeService();
                    $value = $contentTypeService->loadContentType($location->contentInfo->contentTypeId)->identifier;
                    break;
                case 'content_remote_id':
                    $value = $location->contentInfo->remoteId;
                    break;
                case 'current_version':
                case 'current_version_no':
                    $value = $location->contentInfo->currentVersionNo;
                    break;
                case 'depth':
                    $value = $location->depth;
                    break;
                case 'is_hidden':
                    $value = $location->hidden;
                    break;
                case 'main_location_id':
                    $value = $location->contentInfo->mainLocationId;
                    break;
                case 'main_language_code':
                    $value = $location->contentInfo->mainLanguageCode;
                    break;
                case 'modification_date':
                    $value = $location->contentInfo->modificationDate->getTimestamp();
                    break;
                case 'name':
                    $value = $location->contentInfo->name;
                    break;
                case 'owner_id':
                    $value = $location->contentInfo->ownerId;
                    break;
                case 'parent_location_id':
                    $value = $location->parentLocationId;
                    break;
                case 'path':
                    $value = $location->pathString;
                    break;
                case 'priority':
                    $value = $location->priority;
                    break;
                case 'publication_date':
                    $value = $location->contentInfo->publishedDate->getTimestamp();
                    break;
                case 'section_id':
                    $value = $location->contentInfo->sectionId;
                    break;
                case 'section_identifier':
                    $sectionService = $this->repository->getSectionService();
                    $value = $sectionService->loadSection($location->contentInfo->sectionId)->identifier;
                    break;
                case 'sort_field':
                    $value = $this->sortConverter->sortField2Hash($location->sortField);
                    break;
                case 'sort_order':
                    $value = $this->sortConverter->sortOrder2Hash($location->sortOrder);
                    break;
                default:
                    throw new InvalidStepDefinitionException('Location Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }

    /**
     * @param int|string|array $locationKey
     * @return Location
     */
    public function matchLocationByKey($locationKey)
    {
        return $this->locationMatcher->matchOneByKey($locationKey);
    }

    /**
     * NB: weirdly enough, it returns contents, not locations
     *
     * @param string $action
     * @return ContentCollection
     * @throws \Exception
     */
    protected function matchContents($action, $step)
    {
        if (!isset($step->dsl['object_id']) && !isset($step->dsl['remote_id']) && !isset($step->dsl['match'])) {
            throw new InvalidStepDefinitionException("The ID or remote ID of an object or a Match Condition is required to $action a new location.");
        }

        // Backwards compat
        if (!isset($step->dsl['match'])) {
            if (isset($step->dsl['object_id'])) {
                $step->dsl['match'] = array('content_id' => $step->dsl['object_id']);
            } elseif (isset($step->dsl['remote_id'])) {
                $step->dsl['match'] = array('content_remote_id' => $step->dsl['remote_id']);
            }
        }

        $match = $step->dsl['match'];

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

        $offset = isset($step->dsl['match_offset']) ? $this->referenceResolver->resolveReference($step->dsl['match_offset']) : 0;
        $limit = isset($step->dsl['match_limit']) ? $this->referenceResolver->resolveReference($step->dsl['match_limit']) : 0;
        $sort = isset($step->dsl['match_sort']) ? $this->referenceResolver->resolveReference($step->dsl['match_sort']) : array();

        return $this->contentMatcher->matchContent($match, $sort, $offset, $limit);
    }

    protected function setMainLocation(Location $location)
    {
        $contentService = $this->repository->getContentService();
        $contentMetaDataUpdateStruct = $contentService->newContentMetadataUpdateStruct();
        $contentMetaDataUpdateStruct->mainLocationId = $location->id;
        $contentService->updateContentMetadata($location->contentInfo, $contentMetaDataUpdateStruct);
    }

    /**
     * @param $newValue
     * @param int $currentValue
     * @return int|null
     *
     * @todo make protected
     */
    public function getSortField($newValue, $currentValue = null)
    {
        $sortField = $currentValue;

        if ($newValue !== null) {
            $sortField = $this->sortConverter->hash2SortField($newValue);
        }

        return $sortField;
    }

    /**
     * Get the sort order based on the current value and the value in the DSL definition.
     *
     * @see \eZ\Publish\API\Repository\Values\Content\Location::SORT_ORDER_*
     *
     * @param int $newValue
     * @param int $currentValue
     * @return int|null
     *
     * @todo make protected
     */
    public function getSortOrder($newValue, $currentValue = null)
    {
        $sortOrder = $currentValue;

        if ($newValue !== null) {
            $sortOrder = $this->sortConverter->hash2SortOrder($newValue);
        }

        return $sortOrder;
    }
}

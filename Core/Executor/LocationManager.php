<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\Content\Location;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;
use Kaliop\eZMigrationBundle\API\Collection\LocationCollection;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\LocationMatcher;
use Kaliop\eZMigrationBundle\Core\Helper\SortConverter;

/**
 * Handles location migrations.
 */
class LocationManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('location');
    protected $supportedActions = array('create', 'load', 'update', 'delete');

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
            throw new \Exception('Missing parent location id. This is required to create the new location.');
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

        // resolve references and remote ids
        foreach ($parentLocationIds as $id => $parentLocationId) {
            $parentLocationId = $this->referenceResolver->resolveReference($parentLocationId);
            $parentLocationIds[$id] = $this->matchLocationByKey($parentLocationId)->id;
        }

        $contentCollection = $this->matchContents('create', $step);

        $locations = null;
        foreach ($contentCollection as $content) {
            $contentInfo = $content->contentInfo;

            foreach ($parentLocationIds as $parentLocationId) {
                $locationCreateStruct = $locationService->newLocationCreateStruct($parentLocationId);

                if (isset($step->dsl['is_hidden'])) {
                    $locationCreateStruct->hidden = $step->dsl['is_hidden'];
                }

                if (isset($step->dsl['priority'])) {
                    $locationCreateStruct->priority = $step->dsl['priority'];
                }

                if (isset($step->dsl['sort_order'])) {
                    $locationCreateStruct->sortOrder = $this->getSortOrder($step->dsl['sort_order']);
                }

                if (isset($step->dsl['sort_field'])) {
                    $locationCreateStruct->sortField = $this->getSortField($step->dsl['sort_field']);
                }

                $locations[] = $locationService->createLocation($contentInfo, $locationCreateStruct);
            }
        }

        $locationCollection = new LocationCollection($locations);

        $this->setReferences($locationCollection, $step);

        return $locationCollection;
    }

    protected function load($step)
    {
        $locationCollection = $this->matchLocations('load', $step);

        // This check is already done in setReferences
        /*if (count($locationCollection) > 1 && isset($step->dsl['references'])) {
            throw new \Exception("Can not execute Location load because multiple locations match, and a references section is specified in the dsl. References can be set when only 1 location matches");
        }*/

        $this->setReferences($locationCollection, $step);

        return $locationCollection;
    }

    /**
     * Updates information for a location like priority, sort field and sort order.
     * Updates the visibility of the location when needed.
     * Can move a location and its children to a new parent location or swap two locations.
     *
     * @todo add support for flexible matchers
     */
    protected function update($step)
    {
        $locationService = $this->repository->getLocationService();

        $locationCollection = $this->matchLocations('update', $step);

        if (count($locationCollection) > 1 && isset($step->dsl['references'])) {
            throw new \Exception("Can not execute Location update because multiple locations match, and a references section is specified in the dsl. References can be set when only 1 location matches");
        }

        if (count($locationCollection) > 1 && isset($step->dsl['swap_with_location'])) {
            throw new \Exception("Can not execute Location update because multiple locations match, and a swap_with_location is specified in the dsl.");
        }

        // support legacy tag: parent_location_id
        if (isset($step->dsl['swap_with_location']) && (isset($step->dsl['parent_location']) || isset($step->dsl['parent_location_id']))) {
            throw new \Exception('Cannot move location to a new parent and swap location with another location at the same time.');
        }

        foreach ($locationCollection as $key => $location) {

            if (isset($step->dsl['priority'])
                || isset($step->dsl['sort_field'])
                || isset($step->dsl['sort_order'])
                || isset($step->dsl['remote_id'])
            ) {
                $locationUpdateStruct = $locationService->newLocationUpdateStruct();

                    if (isset($step->dsl['priority'])) {
                        $locationUpdateStruct->priority = $step->dsl['priority'];
                    }

                    if (isset($step->dsl['sort_field'])) {
                        $locationUpdateStruct->sortField = $this->getSortField($step->dsl['sort_field'], $location->sortField);
                    }

                    if (isset($step->dsl['sort_order'])) {
                        $locationUpdateStruct->sortOrder = $this->getSortOrder($step->dsl['sort_order'], $location->sortOrder);
                    }

                    if (isset($step->dsl['remote_id'])) {
                        $locationUpdateStruct->remoteId = $step->dsl['remote_id'];
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

                $newParentLocation = $locationService->loadLocation($parentLocationId);

                $locationService->moveSubtree($location, $newParentLocation);
            } elseif (isset($step->dsl['swap_with_location'])) {
                // Swap locations
                $swapLocationId = $step->dsl['swap_with_location'];
                $swapLocationId = $this->referenceResolver->resolveReference($swapLocationId);

                $locationToSwap = $this->matchLocationByKey($swapLocationId);

                $locationService->swapLocation($location, $locationToSwap);
            }

            $locationCollection[$key] = $location;
        }

        $this->setReferences($locationCollection, $step);

        return $locationCollection;
    }

    /**
     * Delete locations
     *
     * @todo add support for flexible matchers
     */
    protected function delete($step)
    {
        $locationService = $this->repository->getLocationService();

        $locationCollection = $this->matchLocations('delete', $step);

        foreach ($locationCollection as $location) {
            $locationService->deleteLocation($location);
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
            throw new \Exception("The id or a match condition is required to $action a location");
        }

        // Backwards compat
        if (isset($step->dsl['match'])) {
            $match = $step->dsl['match'];
        } else {
            $match = array('location_id' => $step->dsl['location_id']);
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($match);

        return $this->locationMatcher->match($match);
    }

    /**
     * Sets references to object attributes
     *
     * The Location Manager currently supports setting references to location id.
     *
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute.
     * @param \eZ\Publish\API\Repository\Values\Content\Location|LocationCollection $location
     * @return boolean
     */
    protected function setReferences($location, $step)
    {
        if (!array_key_exists('references', $step->dsl)) {
            return false;
        }

        if ($location instanceof LocationCollection) {
            if (count($location) > 1) {
                throw new \InvalidArgumentException('Location Manager does not support setting references for creating/updating/loading of multiple locations');
            }
            if (count($location) == 0) {
                throw new \InvalidArgumentException('Location Manager does not support setting references for creating/updating/loading of no locations');
            }

            $location = reset($location);
        }

        foreach ($step->dsl['references'] as $reference) {
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
                /// Q: does this even exist ?
                case 'position':
                    $value = $location->position;
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
                case 'sort_field':
                    $value = $this->sortConverter->sortField2Hash($location->sortField);
                    break;
                case 'sort_order':
                    $value = $this->sortConverter->sortOrder2Hash($location->sortOrder);
                    break;
                default:
                    throw new \InvalidArgumentException('Location Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
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
            throw new \Exception("The ID or remote ID of an object or a Match Condition is required to $action a new location.");
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

        return $this->contentMatcher->matchContent($match);
    }

    /**
     * @param $newValue
     * @param null $currentValue
     * @return int|null
     *
     * * @todo make protected
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

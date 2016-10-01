<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\Content\Location;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;
use Kaliop\eZMigrationBundle\API\Collection\LocationCollection;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\LocationMatcher;

class LocationManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('location');

    protected $contentMatcher;
    protected $locationMatcher;

    public function __construct(ContentMatcher $contentMatcher, LocationMatcher $locationMatcher)
    {
        $this->contentMatcher = $contentMatcher;
        $this->locationMatcher = $locationMatcher;
    }

    /**
     * Method to handle the create operation of the migration instructions
     */
    protected function create()
    {
        $locationService = $this->repository->getLocationService();

        if (!isset($this->dsl['parent_location_id'])) {
            throw new \Exception('Missing parent location id. This is required to create the new location.');
        }
        if (!is_array($this->dsl['parent_location_id'])) {
            $this->dsl['parent_location_id'] = array($this->dsl['parent_location_id']);
        }
        foreach ($this->dsl['parent_location_id'] as $id => $parentLocationId) {
            if ($this->referenceResolver->isReference($parentLocationId)) {
                $this->dsl['parent_location_id'][$id] = $this->referenceResolver->getReferenceValue($parentLocationId);
            }
        }

        $contentCollection = $this->matchContents('create');

        $locations = null;
        foreach ($contentCollection as $content) {
            $contentInfo = $content->contentInfo;

            foreach ($this->dsl['parent_location_id'] as $parentLocationId) {
                $locationCreateStruct = $locationService->newLocationCreateStruct($parentLocationId);

                if (isset($this->dsl['is_hidden'])) {
                    $locationCreateStruct->hidden = $this->dsl['is_hidden'];
                }

                if (isset($this->dsl['priority'])) {
                    $locationCreateStruct->priority = $this->dsl['priority'];
                }

                if (isset($this->dsl['sort_order'])) {
                    $locationCreateStruct->sortOrder = $this->getSortOrder($this->dsl['sort_order']);
                }

                if (isset($this->dsl['sort_field'])) {
                    $locationCreateStruct->sortField = $this->getSortField($this->dsl['sort_field']);
                }

                $locations[] = $locationService->createLocation($contentInfo, $locationCreateStruct);
            }
        }

        $locationCollection = new LocationCollection($locations);

        $this->setReferences($locationCollection);

        return $locationCollection;
    }

    /**
     * Updates basic information for a location like priority, sort field and sort order.
     * Updates the visibility of the location when needed.
     * Can move a location and its children to a new parent location or swap two locations.
     *
     * @todo add support for flexible matchers
     */
    protected function update()
    {
        $locationService = $this->repository->getLocationService();

        $locationCollection = $this->matchLocations('update');

        if (count($locationCollection) > 1 && array_key_exists('references', $this->dsl)) {
            throw new \Exception("Can not execute Location update because multiple contents match, and a references section is specified in the dsl. References can be set when only 1 content matches");
        }

        if (count($locationCollection) > 1 && array_key_exists('swap_with_location', $this->dsl)) {
            throw new \Exception("Can not execute Location update because multiple contents match, and a swap_with_location is specified in the dsl.");
        }

        if (isset($this->dsl['swap_with_location']) && isset($this->dsl['parent_location_id'])) {
            throw new \Exception('Cannot move location to a new parent and swap location with another location at the same time.');
        }

        /*$locationId = $this->dsl['location_id'];
        if ($this->referenceResolver->isReference($locationId)) {
            $locationId = $this->referenceResolver->getReferenceValue($locationId);
        }*/

        foreach ($locationCollection as $key => $location) {
            //$location = $locationService->loadLocation($locationId);

            if (array_key_exists('priority', $this->dsl)
                || array_key_exists('sort_field', $this->dsl)
                || array_key_exists('sort_order', $this->dsl)
                || array_key_exists('remote_id', $this->dsl)
            ) {
                $locationUpdateStruct = $locationService->newLocationUpdateStruct();

                    if (isset($this->dsl['priority'])) {
                        $locationUpdateStruct->priority = $this->dsl['priority'];
                    }

                    if (isset($this->dsl['sort_field'])) {
                        $locationUpdateStruct->sortField = $this->getSortField($this->dsl['sort_field'], $location->sortField);
                    }

                    if (isset($this->dsl['sort_order'])) {
                        $locationUpdateStruct->sortOrder = $this->getSortOrder($this->dsl['sort_order'], $location->sortOrder);
                    }

                    if (isset($this->dsl['remote_id'])) {
                        $locationUpdateStruct->remoteId = $this->dsl['remote_id'];
                    }

                $location = $locationService->updateLocation($location, $locationUpdateStruct);
            }

            // Check if visibility needs to be updated
            if (isset($this->dsl['is_hidden'])) {
                if ($this->dsl['is_hidden']) {
                    $location = $locationService->hideLocation($location);
                } else {
                    $location = $locationService->unhideLocation($location);
                }
            }

            // Move or swap location
            if (isset($this->dsl['parent_location_id'])) {
                // Move the location and all it's children to a new parent
                $parentLocationId = $this->dsl['parent_location_id'];
                if ($this->referenceResolver->isReference($parentLocationId)) {
                    $parentLocationId = $this->referenceResolver->getReferenceValue($parentLocationId);
                }
                $newParentLocation = $locationService->loadLocation($parentLocationId);
                $locationService->moveSubtree($location, $newParentLocation);
            } elseif (isset($this->dsl['swap_with_location'])) {
                //Swap locations
                $swapLocationId = $this->dsl['swap_with_location'];
                if ($this->referenceResolver->isReference($swapLocationId)) {
                    $swapLocationId = $this->referenceResolver->getReferenceValue($swapLocationId);
                }
                $locationToSwap = $locationService->loadLocation($swapLocationId);

                $locationService->swapLocation($location, $locationToSwap);
            }

            $locationCollection[$key] = $location;
        }

        $this->setReferences($locationCollection);

        return $locationCollection;
    }

    /**
     * Delete locations identified by their ids.
     *
     * @todo add support for flexible matchers
     */
    protected function delete()
    {
        $locationService = $this->repository->getLocationService();

        $locationCollection = $this->matchLocations('delete');

        foreach ($locationCollection as $location) {
            //$location = $locationService->loadLocation($locationId);
            $locationService->deleteLocation($location);
        }

        return $locationCollection;
    }

    /**
     * @param string $action
     * @return ContentCollection
     * @throws \Exception
     */
    protected function matchLocations($action)
    {
        if (!isset($this->dsl['location_id'])&& !isset($this->dsl['match'])) {
            throw new \Exception("The ID or a Match Condition is required to $action a location.");
        }

        // Backwards compat
        if (!isset($this->dsl['match'])) {
            $this->dsl['match'] = array('location_id' => $this->dsl['location_id']);
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

        return $this->locationMatcher->match($match);
    }

    /**
     * NB: weirdly enough, it returns contents, not locations
     *
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

    protected function getSortField($newValue, $currentValue = null)
    {
        $sortField = null;

        if (!is_null($currentValue)) {
            $sortField = $currentValue;
        }

        if ($newValue !== null) {
            $sortFieldId = "SORT_FIELD_" . strtoupper($this->dsl['sort_field']);

            $ref = new \ReflectionClass('eZ\Publish\API\Repository\Values\Content\Location');

            $sortField = $ref->getConstant($sortFieldId);
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
     * @return int
     */
    protected function getSortOrder($newValue, $currentValue = null)
    {
        $sortOrder = null;

        if (!is_null($currentValue)) {
            $sortOrder = $currentValue;
        }

        if ($newValue !== null) {
            if (strtoupper($this->dsl['sort_order']) === 'ASC') {
                $sortOrder = Location::SORT_ORDER_ASC;
            } else {
                $sortOrder = Location::SORT_ORDER_DESC;
            }
        }

        return $sortOrder;
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
    protected function setReferences($location)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        if ($location instanceof LocationCollection) {
            if (count($location) > 1) {
                throw new \InvalidArgumentException('Location Manager does not support setting references for creating/updating of multiple locations');
            }
            $location = reset($location);
        }

        foreach ($this->dsl['references'] as $reference) {
            switch ($reference['attribute']) {
                case 'location_id':
                case 'id':
                    $value = $location->id;
                    break;
                case 'remote_id':
                case 'location_remote_id':
                    $value = $location->remoteId;
                    break;
                case 'path':
                    $value = $location->pathString;
                    break;
                default:
                    throw new \InvalidArgumentException('Location Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\Content\Location;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;
use Kaliop\eZMigrationBundle\API\Collection\LocationCollection;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;

class LocationManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('location');

    protected $contentMatcher;

    public function __construct(ContentMatcher $contentMatcher)
    {
        $this->contentMatcher = $contentMatcher;
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

                $locationCreateStruct->hidden = isset($this->dsl['is_hidden']) ?: false;

                if (isset($this->dsl['priority'])) {
                    $locationCreateStruct->priority = $this->dsl['priority'];
                }

                $locationCreateStruct->sortOrder = $this->getSortOrder();
                $locationCreateStruct->sortField = $this->getSortField();

                $locations[] = $locationService->createLocation($contentInfo, $locationCreateStruct);
            }
        }

        $this->setReferences(new LocationCollection($locations));
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

        if (!isset($this->dsl['location_id'])) {
            throw new \Exception('No location set for update.');
        }

        if (isset($this->dsl['swap_with_location']) && isset($this->dsl['patent_location_id'])) {
            throw new \Exception('Cannot move location to a new parent and swap location with another location at the same time.');
        }

        $locationId = $this->dsl['location_id'];
        if ($this->referenceResolver->isReference($locationId)) {
            $locationId = $this->referenceResolver->getReferenceValue($locationId);
        }

        $location = $locationService->loadLocation($locationId);

        if (array_key_exists('priority', $this->dsl)
            || array_key_exists('sort_field', $this->dsl)
            || array_key_exists('sort_order', $this->dsl)
        ) {
            $locationUpdateStruct = $locationService->newLocationUpdateStruct();

            if (isset($this->dsl['priority'])) {
                $locationUpdateStruct->priority = $this->dsl['priority'];
            }

            if (isset($this->dsl['sort_field'])) {
                $locationUpdateStruct->sortField = $this->getSortField($location->sortField);
            }

            if (isset($this->dsl['sort_order'])) {
                $locationUpdateStruct->sortOrder = $this->getSortOrder($location->sortOrder);
            }

            $locationService->updateLocation($location, $locationUpdateStruct);
        }

        // Check if visibility needs to be updated
        if (isset($this->dsl['is_hidden'])) {
            if ($this->dsl['is_hidden']) {
                $locationService->hideLocation($location);
            } else {
                $locationService->unhideLocation($location);
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

        $this->setReferences($location);
    }

    /**
     * Delete locations identified by their ids.
     *
     * @todo add support for flexible matchers
     */
    protected function delete()
    {
        $locationService = $this->repository->getLocationService();

        if (!isset($this->dsl['location_id'])) {
            throw new \Exception('No location provided for deletion');
        }

        if (!is_array($this->dsl['location_id'])) {
            $this->dsl['location_id'] = array($this->dsl['location_id']);
        }

        foreach ($this->dsl['location_id'] as $locationId) {
            $location = $locationService->loadLocation($locationId);
            $locationService->deleteLocation($location);
        }
    }

    protected function getSortField($currentValue = null)
    {
        $sortField = Location::SORT_FIELD_PUBLISHED;

        if (!is_null($currentValue)) {
            $sortField = $currentValue;
        }

        if (isset($this->dsl['sort_field'])) {
            $sortFieldId = "SORT_FIELD_" . strtoupper($this->dsl['sort_field']);

            $ref = new \ReflectionClass('eZ\Publish\API\Repository\Values\Content\Location');

            $sortField = $ref->getConstant($sortFieldId);
        }

        return $sortField;
    }

    /**
     * Get the sort order based on the current value and the value in the DSL definition.
     *
     * If no current value is set and there is no value in the DSL it will default to Location::SORT_ORDER_ASC
     *
     * @see \eZ\Publish\API\Repository\Values\Content\Location::SORT_ORDER_*
     *
     * @param int $currentValue
     * @return int
     */
    protected function getSortOrder($currentValue = null)
    {
        $sortOrder = Location::SORT_ORDER_ASC;
        if (!is_null($currentValue)) {
            $sortOrder = $currentValue;
        }

        if (isset($this->dsl['sort_order'])) {
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
                default:
                    throw new \InvalidArgumentException('Location Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Location;
use Kaliop\eZMigrationBundle\API\Collection\LocationCollection;
use Kaliop\eZMigrationBundle\API\SortingMatcherInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchResultsNumberException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidSortConditionsException;

class LocationMatcher extends QueryBasedMatcher implements SortingMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_DEPTH = 'depth';
    const MATCH_IS_MAIN_LOCATION = 'is_main_location';
    const MATCH_PRIORITY = 'priority';

    protected $allowedConditions = array(
        self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_CONTENT_ID, self::MATCH_LOCATION_ID, self::MATCH_CONTENT_REMOTE_ID, self::MATCH_LOCATION_REMOTE_ID,
        self::MATCH_ATTRIBUTE, self::MATCH_CONTENT_TYPE_ID, self::MATCH_CONTENT_TYPE_IDENTIFIER, self::MATCH_GROUP,
        self::MATCH_CREATION_DATE, self::MATCH_MODIFICATION_DATE, self::MATCH_OBJECT_STATE, self::MATCH_OWNER,
        self::MATCH_PARENT_LOCATION_ID, self::MATCH_PARENT_LOCATION_REMOTE_ID, self::MATCH_QUERY_TYPE, self::MATCH_SECTION,
        self::MATCH_SUBTREE, self::MATCH_VISIBILITY,
        // aliases
        'content_type',
        // BC
        'contenttype_id', 'contenttype_identifier',
        // location-only
        self::MATCH_DEPTH, self::MATCH_IS_MAIN_LOCATION, self::MATCH_PRIORITY,
    );
    protected $returns = 'Location';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param array $sort
     * @param int $offset
     * @param int $limit
     * @param bool $tolerateMisses
     * @return LocationCollection
     * @throws InvalidArgumentException
     * @throws InvalidMatchConditionsException
     * @throws InvalidSortConditionsException
     */
    public function match(array $conditions, array $sort = array(), $offset = 0, $limit = 0, $tolerateMisses = false)
    {
        return $this->matchLocation($conditions, $sort, $offset, $limit, $tolerateMisses);
    }

    /**
     * @param array $conditions
     * @param array $sort
     * @param int $offset
     * @return Location
     * @throws InvalidArgumentException
     * @throws InvalidMatchConditionsException
     * @throws InvalidSortConditionsException
     * @throws InvalidMatchResultsNumberException
     */
    public function matchOne(array $conditions, array $sort = array(), $offset = 0)
    {
        $results = $this->match($conditions, $sort, $offset, 2);
        $count = count($results);
        if ($count !== 1) {
            throw new InvalidMatchResultsNumberException("Found $count " . $this->returns . " when expected exactly only one to match the conditions");
        }

        if ($results instanceof \ArrayObject) {
            $results = $results->getArrayCopy();
        }

        return reset($results);
    }

    /**
     * @param array $conditions key: condition, value: value: int / string / int[] / string[]
     * @param array $sort
     * @param int $offset
     * @param int $limit
     * @param bool $tolerateMisses
     * @return LocationCollection
     * @throws InvalidArgumentException
     * @throws InvalidMatchConditionsException
     * @throws InvalidSortConditionsException
     */
    public function matchLocation(array $conditions, array $sort = array(), $offset = 0, $limit = 0, $tolerateMisses = false)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if ($key == self::MATCH_QUERY_TYPE) {
                $query = $this->getQueryByQueryType($values);
            } else {
                $query = new LocationQuery();
                $query->filter = $this->getQueryCriterion($key, $values);
            }

            // q: when getting a query via QueryType, should we always inject offset/limit?
            $query->limit = $limit != 0 ? $limit : self::INT_MAX_16BIT;
            $query->offset = $offset;
            if (!empty($sort)) {
                $query->sortClauses = $this->getSortClauses($sort);
            }
            if (isset($query->performCount)) $query->performCount = false;

            $results = $this->getSearchService()->findLocations($query);

            $locations = [];
            foreach ($results->searchHits as $result) {
                $locations[$result->valueObject->id] = $result->valueObject;
            }

            return new LocationCollection($locations);
        }
    }

    /**
     * When matching by key, we accept location Id and remote Id only
     * @param int|string $key
     * @return array
     */
    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_LOCATION_ID => $key);
        }
        return array(self::MATCH_LOCATION_REMOTE_ID => $key);
    }

    protected function getQueryCriterion($key, $values)
    {
        if (!is_array($values)) {
            $values = array($values);
        }

        switch ($key) {
            case self::MATCH_DEPTH:
                $match = reset($values);
                $operator = key($values);
                if (!isset(self::$operatorsMap[$operator])) {
                    throw new InvalidMatchConditionsException("Can not use '$operator' as comparison operator for depth");
                }
                return new Query\Criterion\Location\Depth(self::$operatorsMap[$operator], $match);

            case self::MATCH_IS_MAIN_LOCATION:
            case 'is_main':
                /// @todo error/warning if there is more than 1 value...
                $value = reset($values);
                if ($value) {
                    return new Query\Criterion\Location\IsMainLocation(Query\Criterion\Location\IsMainLocation::MAIN);
                } else {
                    return new Query\Criterion\Location\IsMainLocation(Query\Criterion\Location\IsMainLocation::NOT_MAIN);
                }

            case self::MATCH_PRIORITY:
                $match = reset($values);
                $operator = key($values);
                if (!isset(self::$operatorsMap[$operator])) {
                    throw new InvalidMatchConditionsException("Can not use '$operator' as comparison operator for depth");
                }
                return new Query\Criterion\Location\Priority(self::$operatorsMap[$operator], $match);
        }

        return parent::getQueryCriterion($key, $values);
    }

    /**
     * Returns all locations of a set of objects
     *
     * @param int[] $contentIds
     * @return Location[]
     * @deprecated
     */
    protected function findLocationsByContentIds(array $contentIds)
    {
        $locations = [];

        foreach ($contentIds as $contentId) {
            $content = $this->repository->getContentService()->loadContent($contentId);
            foreach ($this->repository->getLocationService()->loadLocations($content->contentInfo) as $location) {
                $locations[$location->id] = $location;
            }
        }

        return $locations;
    }

    /**
     * Returns all locations of a set of objects
     *
     * @param int[] $remoteContentIds
     * @return Location[]
     * @deprecated
     */
    protected function findLocationsByContentRemoteIds(array $remoteContentIds)
    {
        $locations = [];

        foreach ($remoteContentIds as $remoteContentId) {
            $content = $this->repository->getContentService()->loadContentByRemoteId($remoteContentId);
            foreach ($this->repository->getLocationService()->loadLocations($content->contentInfo) as $location) {
                $locations[$location->id] = $location;
            }
        }

        return $locations;
    }

    /**
     * @param int[] $locationIds
     * @return Location[]
     * @deprecated
     */
    protected function findLocationsByLocationIds(array $locationIds)
    {
        $locations = [];

        foreach ($locationIds as $locationId) {
            $locations[$locationId] = $this->repository->getLocationService()->loadLocation($locationId);
        }

        return $locations;
    }

    /**
     * @param int[] $locationRemoteIds
     * @return Location[]
     * @deprecated
     */
    protected function findLocationsByLocationRemoteIds($locationRemoteIds)
    {
        $locations = [];

        foreach ($locationRemoteIds as $locationRemoteId) {
            $location = $this->repository->getLocationService()->loadLocationByRemoteId($locationRemoteId);
            $locations[$location->id] = $location;
        }

        return $locations;
    }

    /**
     * @param int[] $parentLocationIds
     * @return Location[]
     * @deprecated
     */
    protected function findLocationsByParentLocationIds($parentLocationIds)
    {
        $query = new LocationQuery();
        $query->limit = self::INT_MAX_16BIT;
        if (isset($query->performCount)) $query->performCount = false;
        $query->filter = new Query\Criterion\ParentLocationId($parentLocationIds);

        $results = $this->getSearchService()->findLocations($query);

        $locations = [];

        foreach ($results->searchHits as $result) {
            $locations[$result->valueObject->id] = $result->valueObject;
        }

        return $locations;
    }

    /**
     * @param int[] $parentLocationRemoteIds
     * @return Location[]
     * @deprecated
     */
    protected function findLocationsByParentLocationRemoteIds($parentLocationRemoteIds)
    {
        $locationIds = [];

        foreach ($parentLocationRemoteIds as $parentLocationRemoteId) {
            $location = $this->repository->getLocationService()->loadLocationByRemoteId($parentLocationRemoteId);
            $locationIds[$location->id] = $location->id;
        }

        return $this->findLocationsByParentLocationIds($locationIds);
    }
}

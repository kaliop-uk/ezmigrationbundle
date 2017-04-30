<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\Content\Query;
use Kaliop\eZMigrationBundle\API\Collection\LocationCollection;
use eZ\Publish\API\Repository\Values\Content\LocationQuery;

/**
 * @todo extend to allow matching by visibility, subtree, depth, object state, section, creation/modification date...
 * @todo optimize the matches on multiple conditions (and, or) by compiling them in a single query
 */
class LocationMatcher extends RepositoryMatcher
{
    use FlexibleKeyMatcherTrait;

    const MATCH_CONTENT_ID = 'content_id';
    const MATCH_LOCATION_ID = 'location_id';
    const MATCH_CONTENT_REMOTE_ID = 'content_remote_id';
    const MATCH_LOCATION_REMOTE_ID = 'location_remote_id';
    const MATCH_PARENT_LOCATION_ID = 'parent_location_id';
    const MATCH_PARENT_LOCATION_REMOTE_ID = 'parent_location_remote_id';
    const MATCH_CONTENT_TYPE_ID = 'contenttype_id';
    const MATCH_CONTENT_TYPE_IDENTIFIER = 'contenttype_identifier';

    protected $allowedConditions = array(
        self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_CONTENT_ID, self::MATCH_LOCATION_ID, self::MATCH_CONTENT_REMOTE_ID, self::MATCH_LOCATION_REMOTE_ID,
        self::MATCH_PARENT_LOCATION_ID, self::MATCH_PARENT_LOCATION_REMOTE_ID
    );
    protected $returns = 'Location';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return LocationCollection
     */
    public function match(array $conditions)
    {
        return $this->matchLocation($conditions);
    }

    /**
     * @param array $conditions key: condition, value: value: int / string / int[] / string[]
     * @return LocationCollection
     */
    public function matchLocation(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            $query = new LocationQuery();
            $query->limit = PHP_INT_MAX;
            $query->filter = $this->getQueryCriterion($key, $values);
            $results = $this->repository->getSearchService()->findLocations($query);

            $locations = [];
            foreach ($results->searchHits as $result) {
                $locations[$result->valueObject->id] = $result->valueObject;
            }

            return new LocationCollection($locations);
        }

        /*foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case self::MATCH_CONTENT_ID:
                   return new LocationCollection($this->findLocationsByContentIds($values));

                case self::MATCH_LOCATION_ID:
                    return new LocationCollection($this->findLocationsByLocationIds($values));

                case self::MATCH_CONTENT_REMOTE_ID:
                    return new LocationCollection($this->findLocationsByContentRemoteIds($values));

                case self::MATCH_LOCATION_REMOTE_ID:
                    return new LocationCollection($this->findLocationsByLocationRemoteIds($values));

                case self::MATCH_PARENT_LOCATION_ID:
                    return new LocationCollection($this->findLocationsByParentLocationIds($values));

                case self::MATCH_PARENT_LOCATION_REMOTE_ID:
                    return new LocationCollection($this->findLocationsByParentLocationRemoteIds($values));

                case self::MATCH_AND:
                    return $this->matchAnd($values);

                case self::MATCH_OR:
                    return $this->matchOr($values);
            }
        }*/
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
            $locations = array_merge($locations, $this->repository->getLocationService()->loadLocations($content->contentInfo));
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
            $locations = array_merge($locations, $this->repository->getLocationService()->loadLocations($content->contentInfo));
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
            $locations[] = $this->repository->getLocationService()->loadLocation($locationId);
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
            $locations[] = $this->repository->getLocationService()->loadLocationByRemoteId($locationRemoteId);
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
        $query->limit = PHP_INT_MAX;
        $query->filter = new Query\Criterion\ParentLocationId($parentLocationIds);

        $results = $this->repository->getSearchService()->findLocations($query);

        $locations = [];

        foreach ($results->searchHits as $result) {
            $locations[] = $result->valueObject;
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
            $locationIds[] = $location->id;
        }

        return $this->findLocationsByParentLocationIds($locationIds);
    }

    /**
     * @param $key
     * @param $values
     * @return mixed should it be \eZ\Publish\API\Repository\Values\Content\Query\CriterionInterface ?
     * @throws \Exception for unsupported keys
     */
    protected function getQueryCriterion($key, $values)
    {
        if (!is_array($values)) {
            $values = array($values);
        }

        switch ($key) {
            case self::MATCH_CONTENT_ID:
                return new Query\Criterion\ContentId($values);

            case self::MATCH_LOCATION_ID:
                return new Query\Criterion\LocationId($values);

            case self::MATCH_CONTENT_REMOTE_ID:
                return new Query\Criterion\RemoteId(reset($values));

            case self::MATCH_LOCATION_REMOTE_ID:
                return new Query\Criterion\LocationRemoteId($values);

            case self::MATCH_PARENT_LOCATION_ID:
                return new Query\Criterion\ParentLocationId($values);

            case self::MATCH_PARENT_LOCATION_REMOTE_ID:
                $locationIds = [];
                foreach ($values as $remoteParentLocationId) {
                    $location = $this->repository->getLocationService()->loadLocationByRemoteId($remoteParentLocationId);
                    // unique locations
                    $locationIds[$location->id] = $location->id;
                }
                return new Query\Criterion\ParentLocationId($locationIds);

            case 'content_type_id':
            case self::MATCH_CONTENT_TYPE_ID:
                return new Query\Criterion\ContentTypeId($values);

            case 'content_type_identifier':
            case self::MATCH_CONTENT_TYPE_IDENTIFIER:
                return new Query\Criterion\ContentTypeIdentifier($values);

            case self::MATCH_AND:
                $subCriteria = array();
                foreach($values as $subCriterion) {
                    $value = reset($subCriterion);
                    $subCriteria[] = $this->getQueryCriterion(key($subCriterion), $value);
                }
                return new Query\Criterion\LogicalAnd($subCriteria);

            case self::MATCH_OR:
                $subCriteria = array();
                foreach($values as $subCriterion) {
                    $value = reset($subCriterion);
                    $subCriteria[] = $this->getQueryCriterion(key($subCriterion), $value);
                }
                return new Query\Criterion\LogicalOr($subCriteria);

            case self::MATCH_NOT:
                /// @todo throw if more than one sub-criteria found
                $subCriterion = reset($values);
                $value = reset($subCriterion);
                $subCriterion = $this->getQueryCriterion(key($subCriterion), $value);
                return new Query\Criterion\LogicalNot($subCriterion);

            default:
                throw new \Exception($this->returns . " can not be matched because matching condition '$key' is not supported. Supported conditions are: " .
                    implode(', ', $this->allowedConditions));
        }
    }
}

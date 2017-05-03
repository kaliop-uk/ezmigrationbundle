<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Query;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;

/**
 * @todo extend to allow matching by subtree, depth, object state, creation/modification date...
 */
class ContentMatcher extends QueryBasedMatcher
{
    use FlexibleKeyMatcherTrait;

    protected $allowedConditions = array(
        self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_CONTENT_ID, self::MATCH_LOCATION_ID, self::MATCH_CONTENT_REMOTE_ID, self::MATCH_LOCATION_REMOTE_ID,
        self::MATCH_CONTENT_TYPE_ID, self::MATCH_CONTENT_TYPE_IDENTIFIER, self::MATCH_CREATION_DATE,
        self::MATCH_MODIFICATION_DATE, self::MATCH_OBJECT_STATE, self::MATCH_PARENT_LOCATION_ID,
        self::MATCH_PARENT_LOCATION_REMOTE_ID, self::MATCH_SECTION, self::MATCH_SUBTREE, self::MATCH_VISIBILITY,
        // aliases
        'content_type', 'content_type_id', 'content_type_identifier'
    );
    protected $returns = 'Content';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ContentCollection
     */
    public function match(array $conditions)
    {
        return $this->matchContent($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ContentCollection
     */
    public function matchContent(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            // BC support
            if ($key == 'content_type') {
                if (is_int($values[0]) || ctype_digit($values[0])) {
                    $key = self::MATCH_CONTENT_TYPE_ID;
                } else {
                    $key = self::MATCH_CONTENT_TYPE_IDENTIFIER;
                }
            }

            $query = new Query();
            $query->limit = PHP_INT_MAX;
            $query->filter = $this->getQueryCriterion($key, $values);
            switch ($key) {
                case 'content_type_id':
                case self::MATCH_CONTENT_TYPE_ID:
                case 'content_type_identifier':
                case self::MATCH_CONTENT_TYPE_IDENTIFIER:
                    // sort objects by depth, lower to higher, so that deleting them has less chances of failure
                    // NB: we only do this in eZP versions that allow depth sorting on content queries
                    if (class_exists('eZ\Publish\API\Repository\Values\Content\Query\SortClause\LocationDepth')) {
                        $query->sortClauses = array(new Query\SortClause\LocationDepth(Query::SORT_DESC));
                    }
            }
            $results = $this->repository->getSearchService()->findContent($query);

            $contents = [];
            foreach ($results->searchHits as $result) {
                // make sure we return every object only once
                $contents[$result->valueObject->contentInfo->id] = $result->valueObject;
            }

            return new ContentCollection($contents);
        }
    }

    /**
     * When matching by key, we accept content Id and remote Id only
     * @param int|string $key
     * @return array
     */
    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_CONTENT_ID => $key);
        }
        return array(self::MATCH_CONTENT_REMOTE_ID => $key);
    }

    /**
     * @param int[] $contentIds
     * @return Content[]
     * @deprecated
     */
    protected function findContentsByContentIds(array $contentIds)
    {
        $contents = [];

        foreach ($contentIds as $contentId) {
            // return unique contents
            $content = $this->repository->getContentService()->loadContent($contentId);
            $contents[$content->contentInfo->id] = $content;
        }

        return $contents;
    }

    /**
     * @param string[] $remoteContentIds
     * @return Content[]
     * @deprecated
     */
    protected function findContentsByContentRemoteIds(array $remoteContentIds)
    {
        $contents = [];

        foreach ($remoteContentIds as $remoteContentId) {
            // return unique contents
            $content = $this->repository->getContentService()->loadContentByRemoteId($remoteContentId);
            $contents[$content->contentInfo->id] = $content;
        }

        return $contents;
    }

    /**
     * @param int[] $locationIds
     * @return Content[]
     * @deprecated
     */
    protected function findContentsByLocationIds(array $locationIds)
    {
        $contentIds = [];

        foreach ($locationIds as $locationId) {
            $location = $this->repository->getLocationService()->loadLocation($locationId);
            // return unique ids
            $contentIds[$location->contentId] = $location->contentId;
        }

        return $this->findContentsByContentIds($contentIds);
    }

    /**
     * @param string[] $remoteLocationIds
     * @return Content[]
     * @deprecated
     */
    protected function findContentsByLocationRemoteIds($remoteLocationIds)
    {
        $contentIds = [];

        foreach ($remoteLocationIds as $remoteLocationId) {
            $location = $this->repository->getLocationService()->loadLocationByRemoteId($remoteLocationId);
            // return unique ids
            $contentIds[$location->contentId] = $location->contentId;
        }

        return $this->findContentsByContentIds($contentIds);
    }

    /**
     * @param int[] $parentLocationIds
     * @return Content[]
     * @deprecated
     */
    protected function findContentsByParentLocationIds($parentLocationIds)
    {
        $query = new Query();
        $query->limit = PHP_INT_MAX;
        $query->filter = new Query\Criterion\ParentLocationId($parentLocationIds);
        $results = $this->repository->getSearchService()->findContent($query);

        $contents = [];
        foreach ($results->searchHits as $result) {
            // make sure we return every object only once
            $contents[$result->valueObject->contentInfo->id] = $result->valueObject;
        }

        return $contents;
    }

    /**
     * @param string[] $remoteParentLocationIds
     * @return Content[]
     * @deprecated
     */
    protected function findContentsByParentLocationRemoteIds($remoteParentLocationIds)
    {
        $locationIds = [];

        foreach ($remoteParentLocationIds as $remoteParentLocationId) {
            $location = $this->repository->getLocationService()->loadLocationByRemoteId($remoteParentLocationId);
            // unique locations
            $locationIds[$location->id] = $location->id;
        }

        return $this->findContentsByParentLocationIds($locationIds);
    }

    /**
     * @param string[] $contentTypeIdentifiers
     * @return Content[]
     * @deprecated
     */
    protected function findContentsByContentTypeIdentifiers(array $contentTypeIdentifiers)
    {
        $query = new Query();
        $query->limit = PHP_INT_MAX;
        $query->filter = new Query\Criterion\ContentTypeIdentifier($contentTypeIdentifiers);
        // sort objects by depth, lower to higher, so that deleting them has less chances of failure
        // NB: we only do this in eZP versions that allow depth sorting on content queries
        if (class_exists('eZ\Publish\API\Repository\Values\Content\Query\SortClause\LocationDepth')) {
            $query->sortClauses = array(new Query\SortClause\LocationDepth(Query::SORT_DESC));
        }

        $results = $this->repository->getSearchService()->findContent($query);

        $contents = [];
        foreach ($results->searchHits as $result) {
            // make sure we return every object only once
            $contents[$result->valueObject->contentInfo->id] = $result->valueObject;
        }

        return $contents;
    }

    /**
     * @param int[] $contentTypeIds
     * @return Content[]
     * @deprecated
     */
    protected function findContentsByContentTypeIds(array $contentTypeIds)
    {
        $query = new Query();
        $query->limit = PHP_INT_MAX;
        $query->filter = new Query\Criterion\ContentTypeId($contentTypeIds);
        // sort objects by depth, lower to higher, so that deleting them has less chances of failure
        // NB: we only do this in eZP versions that allow depth sorting on content queries
        if (class_exists('eZ\Publish\API\Repository\Values\Content\Query\SortClause\LocationDepth')) {
            $query->sortClauses = array(new Query\SortClause\LocationDepth(Query::SORT_DESC));
        }
        $results = $this->repository->getSearchService()->findContent($query);

        $contents = [];
        foreach ($results->searchHits as $result) {
            // make sure we return every object only once
            $contents[$result->valueObject->contentInfo->id] = $result->valueObject;
        }

        return $contents;
    }

}

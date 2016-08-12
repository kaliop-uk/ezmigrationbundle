<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Query;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;

/**
 * @todo extend to allow matching by visibility, subtree, depth, object state, section, creation/modification date...
 * @todo extend to allow matching on multiple conditions (AND)
 */
class ContentMatcher extends AbstractMatcher
{
    const MATCH_CONTENT_ID = 'content_id';
    const MATCH_LOCATION_ID = 'location_id';
    const MATCH_CONTENT_REMOTE_ID = 'content_remote_id';
    const MATCH_LOCATION_REMOTE_ID = 'location_remote_id';
    const MATCH_PARENT_LOCATION_ID = 'parent_location_id';
    const MATCH_PARENT_LOCATION_REMOTE_ID = 'parent_location_remote_id';
    const MATCH_CONTENT_TYPE_IDENTIFIER = 'content_type';

    protected $allowedConditions = array(
        self::MATCH_CONTENT_ID, self::MATCH_LOCATION_ID, self::MATCH_CONTENT_REMOTE_ID, self::MATCH_LOCATION_REMOTE_ID,
        self::MATCH_PARENT_LOCATION_ID, self::MATCH_PARENT_LOCATION_REMOTE_ID, self::MATCH_CONTENT_TYPE_IDENTIFIER
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

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case self::MATCH_CONTENT_ID:
                   return new ContentCollection($this->findContentsByContentIds($values));

                case self::MATCH_LOCATION_ID:
                    return new ContentCollection($this->findContentsByLocationIds($values));

                case self::MATCH_CONTENT_REMOTE_ID:
                    return new ContentCollection($this->findContentsByContentRemoteIds($values));

                case self::MATCH_LOCATION_REMOTE_ID:
                    return new ContentCollection($this->findContentsByLocationRemoteIds($values));

                case self::MATCH_PARENT_LOCATION_ID:
                    return new ContentCollection($this->findContentsByParentLocationIds($values));

                case self::MATCH_PARENT_LOCATION_REMOTE_ID:
                    return new ContentCollection($this->findContentsByParentLocationRemoteIds($values));

                case self::MATCH_CONTENT_TYPE_IDENTIFIER:
                    return new ContentCollection($this->findContentsByContentTypeIdentifiers($values));
            }
        }
    }

    /**
     * @param int[] $contentIds
     * @return Content[]
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
     */
    protected function findContentsByContentTypeIdentifiers(array $contentTypeIdentifiers)
    {
        $query = new Query();
        $query->limit = PHP_INT_MAX;
        $query->filter = new Query\Criterion\ContentTypeIdentifier($contentTypeIdentifiers);
        // sort objects by depth, lower to higher, so that deleting them has less chances of failure
        /// @todo replace with a location querty, as we are using deprecated functionality
        $query->sortClauses = array(new Query\SortClause\LocationDepth(Query::SORT_DESC));
        $results = $this->repository->getSearchService()->findContent($query);

        $contents = [];
        foreach ($results->searchHits as $result) {
            // make sure we return every object only once
            $contents[$result->valueObject->contentInfo->id] = $result->valueObject;
        }

        return $contents;
    }
}

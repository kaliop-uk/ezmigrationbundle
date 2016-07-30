<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Query;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;

class ContentMatcher
{
    const MATCH_CONTENT_ID = 'content_id';
    const MATCH_LOCATION_ID = 'location_id';
    const MATCH_CONTENT_REMOTE_ID = 'content_remote_id';
    const MATCH_LOCATION_REMOTE_ID = 'location_remote_id';
    const MATCH_PARENT_LOCATION_ID = 'parent_location_id';
    const MATCH_PARENT_LOCATION_REMOTE_ID = 'parent_location_remote_id';

    protected $repository;

    /**
     * @param Repository $repository
     * @todo inject the services needed, not thw whole repository
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array $conditions key: condition, value: int / array of ints
     * @return ContentCollection
     */
    public function matchContent(array $conditions)
    {
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
            }
        }
    }

    /**
     * @param int[] $contentIds
     *
     * @return Content[]
     */
    protected function findContentsByContentIds(array $contentIds)
    {
        $contents = [];

        foreach ($contentIds as $contentId) {
            $contents[] = $this->repository->getContentService()->loadContent($contentId);
        }

        return $contents;
    }

    /**
     * @param int[] $remoteContentIds
     *
     * @return Content[]
     */
    protected function findContentsByContentRemoteIds(array $remoteContentIds)
    {
        $contents = [];

        foreach ($remoteContentIds as $remoteContentId) {
            $contents[] = $this->repository->getContentService()->loadContentByRemoteId($remoteContentId);
        }

        return $contents;
    }

    /**
     * @param int[] $locationIds
     *
     * @return Content[]
     */
    protected function findContentsByLocationIds(array $locationIds)
    {
        $contentIds = [];

        foreach ($locationIds as $locationId) {
            $location = $this->repository->getLocationService()->loadLocation($locationId);
            $contentIds[] = $location->contentId;
        }

        return $this->findContentsByContentIds($contentIds);
    }

    /**
     * @param int[] $remoteLocationIds
     *
     * @return Content[]
     */
    protected function findContentsByLocationRemoteIds($remoteLocationIds)
    {
        $contentIds = [];

        foreach ($remoteLocationIds as $remoteLocationId) {
            $location = $this->repository->getLocationService()->loadLocationByRemoteId($remoteLocationId);
            $contentIds[] = $location->contentId;
        }

        return $this->findContentsByContentIds($contentIds);
    }

    /**
     * @param int[] $parentLocationIds
     *
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
            $contents[] = $result->valueObject;
        }

        return $contents;
    }

    /**
     * @param int[] $remoteParentLocationIds
     *
     * @return Content[]
     */
    protected function findContentsByParentLocationRemoteIds($remoteParentLocationIds)
    {
        $locationIds = [];

        foreach ($remoteParentLocationIds as $remoteParentLocationId) {
            $location = $this->repository->getLocationService()->loadLocationByRemoteId($remoteParentLocationId);
            $locationIds[] = $location->id;
        }

        return $this->findContentsByParentLocationIds($locationIds);
    }
}

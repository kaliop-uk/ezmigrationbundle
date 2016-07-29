<?php

namespace Kaliop\eZMigrationBundle\Core\API;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Query;

class ContentMatcher
{
    protected $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array $conditions
     * @return ContentCollection
     */
    public function getCollection(array $conditions)
    {
        $contents = $this->matchContentByConditions($conditions);

        return new ContentCollection($contents);
    }

    /**
     * @param array $conditions
     */
    public function matchContentByConditions(array $conditions)
    {
        foreach ($conditions as $key => $values) {

            if (!is_int($values) && !is_array($values)) {
                throw new \Exception('Value must be an integer or an array');
            }

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'content_id':
                   return $this->findContentsByContentIds($values);

                case 'location_id':
                    return $this->findContentsByLocationIds($values);

                case 'content_remote_id':
                    return $this->findContentsByRemoteContentIds($values);

                case 'location_remote_id':
                    return $this->findContentsByRemoteLocationIds($values);

                case 'parent_location_id':
                    return $this->findContentsByParentLocationIds($values);

                case 'parent_remote_location_id':
                    return $this->findContentsByParentRemoteLocationIds($values);
            }
        }
    }

    /**
     * @param int[] $contentIds
     *
     * @return Content[]
     */
    private function findContentsByContentIds(array $contentIds)
    {
        $contents = [];

        foreach ($contentIds as $contentId) {
            try {
                $contents[] = $this->repository->getContentService()->loadContent($contentId);
            }
            catch (\Exception $e) {
                // @todo log from here?
            }
        }

        return $contents;
    }

    /**
     * @param int[] $remoteContentIds
     *
     * @return Content[]
     */
    private function findContentsByRemoteContentIds(array $remoteContentIds)
    {
        $contents = [];

        foreach ($remoteContentIds as $remoteContentId) {
            try {
                $contents[] = $this->repository->getContentService()->loadContentByRemoteId($remoteContentId);
            }
            catch (\Exception $e) {
                // @todo log from here?
            }
        }

        return $contents;
    }

    /**
     * @param int[] $locationIds
     *
     * @return Content[]
     */
    private function findContentsByLocationIds(array $locationIds)
    {
        $contentIds = [];

        foreach ($locationIds as $locationId) {
            try {
                $location = $this->repository->getLocationService()->loadLocation($locationId);
                $contentIds[] = $location->contentId;
            }
            catch (\Exception $e) {
                // @todo log from here
            }
        }

        return $this->findContentsByContentIds($contentIds);
    }

    /**
     * @param int[] $remoteLocationIds
     *
     * @return Content[]
     */
    private function findContentsByRemoteLocationIds($remoteLocationIds)
    {
        $contentIds = [];

        foreach ($remoteLocationIds as $remoteLocationId) {
            try {
                $location = $this->repository->getLocationService()->loadLocationByRemoteId($remoteLocationId);
                $contentIds[] = $location->contentId;
            }
            catch (\Exception $e) {
                // @todo log from here
            }
        }

        return $this->findContentsByContentIds($contentIds);
    }

    /**
     * @param int[] $parentLocationIds
     *
     * @return Content[]
     */
    private function findContentsByParentLocationIds($parentLocationIds)
    {
        $query = new Query();
        $query->limit = PHP_INT_MAX;
        $query->filter = new Query\Criterion\ParentLocationId($parentLocationIds);

        $results = $this->repository->getSearchService()->findContentInfo($query);

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
    private function findContentsByParentRemoteLocationIds($remoteParentLocationIds)
    {
        $locationIds = [];

        foreach ($remoteParentLocationIds as $remoteParentLocationId) {
            $location = $this->repository->getLocationService()->loadLocationByRemoteId($remoteParentLocationId);
            $locationIds[] = $location->id;
        }

        return $this->findContentsByParentLocationIds($locationIds);
    }
}
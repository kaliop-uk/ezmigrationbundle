<?php

namespace Kaliop\eZMigrationBundle\Core\API;

use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\SearchService;

class ContentMatcher
{
    protected $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array $conditions
     */
    public function matchContentByConditions($conditions = array())
    {
        $contents = [];

        foreach ($conditions as $key => $values) {
            switch ($key) {
                case 'content_id':
                    $contents = array_merge($contents, $this->findContentsByContentId($values));
                    break;

                case 'location_id':
                    $contents = array_merge($contents, $this->findContentsByLocationId($values));
                    break;

                case 'content_remote_id':
                    $contents = array_merge($contents, $this->findContentsByRemoteContentId($values));
                    break;

                case 'location_remote_id':
                    $contents = array_merge($contents, $this->findContentsByRemoteLocationId($values));
                    break;

                case 'parent_location_id':
                    $contents = array_merge($contents, $this->findContentsByParentLocationId($values));
                    break;

                case 'parent_remote_location_id':
                    $contents = array_merge($contents, $this->findContentsByParentLocationId($values));
                    break;

            }
        }

        return new ContentCollection($contents);
    }

    /**
     * @param int|int[] $contentIds
     */
    public function findContentsByContentId($contentIds)
    {

    }

    /**
     * @param int|int[] $remoteContentIds
     */
    public function findContentsByRemoteContentId($remoteContentIds)
    {

    }

    /**
     * @param int|int[] $locationIds
     */
    public function findContentsByLocationId($locationIds)
    {

    }

    /**
     * @param int|int[] $remoteLocationIds
     */
    public function findContentsByRemoteLocationId($remoteLocationIds)
    {

    }

    /**
     * @param int $parentLocationId
     */
    public function findContentsByParentLocationId($parentLocationId)
    {

    }

    /**
     * @param int $remoteParentLocationId
     */
    public function findContentsByRemoteParentLocationId($remoteParentLocationId)
    {

    }
}
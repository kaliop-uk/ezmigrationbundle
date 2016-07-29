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
                throw new \Exception('Value must be an integer or an array')
            }

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'content_id':
                    $contents = $this->findContentsByContentIds($values);

                case 'location_id':
                    return $this->findContentsByLocationIds($values);

                case 'content_remote_id':
                    return $this->findContentsByRemoteContentIds($values);

                case 'location_remote_id':
                    return $this->findContentsByRemoteLocationIds($values);

                case 'parent_location_id':
                    return $this->findContentsByParentLocationIds($values);

                case 'parent_remote_location_id':
                    return $this->findContentsByParentLocationIds($values);
            }
        }
    }

    /**
     * @param int[] $contentIds
     */
    private function findContentsByContentIds(array $contentIds)
    {

    }

    /**
     * @param int[] $remoteContentIds
     */
    private function findContentsByRemoteContentIds(array $remoteContentIds)
    {

    }

    /**
     * @param int[] $locationIds
     */
    private function findContentsByLocationIds(array $locationIds)
    {

    }

    /**
     * @param int[] $remoteLocationIds
     */
    private function findContentsByRemoteLocationIds($remoteLocationIds)
    {

    }

    /**
     * @param int[] $parentLocationIds
     */
    private function findContentsByParentLocationIds($parentLocationIds)
    {

    }

    /**
     * @param int[] $remoteParentLocationIds
     */
    private function findContentsByRemoteParentLocationIds($remoteParentLocationIds)
    {

    }
}
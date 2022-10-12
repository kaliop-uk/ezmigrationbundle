<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidSortConditionsException;

class ContentMatcherDirectLoad extends ContentMatcher
{
    /**
     * Override the parent's implementation to use the repository instead of the Search Service to load Contents when
     * specified by Id or RemoteId.
     * This has the advantage of not going through Solr, and hence having less problems with transactions and indexation delay.
     * NB: only throws if no contents are matching when matching by content id / rid...
     *
     * @param array $conditions
     * @param array $sort
     * @param int $offset
     * @param int $limit
     * @param bool $tolerateMisses
     * @return ContentCollection
     * @throws InvalidArgumentException
     * @throws InvalidMatchConditionsException
     * @throws InvalidSortConditionsException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function matchContent(array $conditions, array $sort = array(), $offset = 0, $limit = 0, $tolerateMisses = false)
    {
        $match = reset($conditions);
        if (count($conditions) === 1 && in_array(($key = key($conditions)), array(self::MATCH_CONTENT_ID, self::MATCH_CONTENT_REMOTE_ID))) {
            $match = (array)$match;
            $contents = array();
            switch ($key) {
                case self::MATCH_CONTENT_ID:
                    foreach ($match as $contentId) {
                        try {
                            $content = $this->repository->getContentService()->loadContent($contentId);
                            $contents[$content->id] = $content;
                        } catch (NotFoundException $e) {
                            if (!$tolerateMisses) {
                                throw $e;
                            }
                        }
                    }
                    break;
                case self::MATCH_CONTENT_REMOTE_ID:
                    foreach ($match as $contentRemoteId) {
                        try {
                            $content = $this->repository->getContentService()->loadContentByRemoteId($contentRemoteId);
                            $contents[$content->id] = $content;
                        } catch (NotFoundException $e) {
                            if (!$tolerateMisses) {
                                throw $e;
                            }
                        }
                    }
                    break;
            }
            return new ContentCollection($contents);
        }

        return parent::matchContent($conditions, $sort, $offset, $limit);
    }
}

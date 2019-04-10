<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\Query;
use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidSortConditionsException;
use Kaliop\eZMigrationBundle\API\SortingMatcherInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchResultsNumberException;

class ContentMatcher extends QueryBasedMatcher implements SortingMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_RELATES_TO = 'relates_to';
    const MATCH_RELATED_FROM = 'related_from';

    protected $allowedConditions = array(
        self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_CONTENT_ID, self::MATCH_LOCATION_ID, self::MATCH_CONTENT_REMOTE_ID, self::MATCH_LOCATION_REMOTE_ID,
        self::MATCH_ATTRIBUTE, self::MATCH_CONTENT_TYPE_ID, self::MATCH_CONTENT_TYPE_IDENTIFIER, self::MATCH_GROUP,
        self::MATCH_CREATION_DATE, self::MATCH_MODIFICATION_DATE, self::MATCH_OBJECT_STATE, self::MATCH_OWNER,
        self::MATCH_PARENT_LOCATION_ID, self::MATCH_PARENT_LOCATION_REMOTE_ID, self::MATCH_SECTION, self::MATCH_SUBTREE,
        self::MATCH_VISIBILITY,
        // aliases
        'content_type', 'content_type_id', 'content_type_identifier',
        // content-only
        self::MATCH_RELATES_TO, self::MATCH_RELATED_FROM
    );
    protected $returns = 'Content';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param array $sort
     * @param int $offset
     * @param int $limit
     * @return ContentCollection
     * @throws InvalidMatchConditionsException
     * @throws InvalidSortConditionsException
     */
    public function match(array $conditions, array $sort = array(), $offset = 0, $limit = 0)
    {
        return $this->matchContent($conditions, $sort, $offset, $limit);
    }

    /**
     * @param array $conditions
     * @param array $sort
     * @param int $offset
     * @return Content
     * @throws InvalidMatchResultsNumberException
     * @throws InvalidMatchConditionsException
     * @throws InvalidSortConditionsException
     */
    public function matchOne(array $conditions, array $sort = array(), $offset = 0)
    {
        $results = $this->match($conditions, $sort, $offset, 2);
        $count = count($results);
        if ($count !== 1) {
            throw new InvalidMatchResultsNumberException("Found $count " . $this->returns . " when expected exactly only one to match the conditions");
        }
        return reset($results);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param array $sort
     * @param int $offset
     * @param int $limit
     * @return ContentCollection
     * @throws InvalidMatchConditionsException
     * @throws InvalidSortConditionsException
     */
    public function matchContent(array $conditions, array $sort = array(), $offset = 0, $limit = 0)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            switch($key) {

                case self::MATCH_RELATES_TO:
                    $contentService = $this->repository->getContentService();
                    $contents = array();
                    // allow to specify the objects to relate to using different ways
                    $relatedContents = $this->match($values);
                    foreach($relatedContents as $relatedContent) {
                        foreach($contentService->loadReverseRelations($relatedContent->contentInfo) as $relatingContent) {
                            $contents[$relatingContent->contentInfo->id] = $relatingContent;
                        }
                    }
                    break;

                case self::MATCH_RELATED_FROM:
                    $contentService = $this->repository->getContentService();
                    $contents = array();
                    // allow to specify the objects we relate to using different ways
                    $relatingContents = $this->match($values);
                    foreach($relatingContents as $relatingContent) {
                        foreach($contentService->loadRelations($relatingContent->contentInfo) as $relatedContent) {
                            $contents[$relatedContent->contentInfo->id] = $relatedContent;
                        }
                    }
                    break;

                default:

                    // BC support
                    if ($key == 'content_type') {
                        if (is_int($values[0]) || ctype_digit($values[0])) {
                            $key = self::MATCH_CONTENT_TYPE_ID;
                        } else {
                            $key = self::MATCH_CONTENT_TYPE_IDENTIFIER;
                        }
                    }

                    $query = new Query();
                    $query->limit = $limit != 0 ? $limit : self::INT_MAX_16BIT;
                    $query->offset = $offset;
                    if (isset($query->performCount)) $query->performCount = false;
                    $query->filter = $this->getQueryCriterion($key, $values);
                    if (!empty($sort)) {
                        $query->sortClauses = $this->getSortClauses($sort);
                    } else {
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
                    }

                    $results = $this->repository->getSearchService()->findContent($query);

                    $contents = [];
                    foreach ($results->searchHits as $result) {
                        // make sure we return every object only once
                        $contents[$result->valueObject->contentInfo->id] = $result->valueObject;
                    }
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
        $query->limit = self::INT_MAX_16BIT;
        if (isset($query->performCount)) $query->performCount = false;
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
        $query->limit = self::INT_MAX_16BIT;
        if (isset($query->performCount)) $query->performCount = false;
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
        $query->limit = self::INT_MAX_16BIT;
        if (isset($query->performCount)) $query->performCount = false;
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

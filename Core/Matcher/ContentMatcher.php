<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;
use eZ\Publish\API\Repository\Exceptions\NotImplementedException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
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
        self::MATCH_PARENT_LOCATION_ID, self::MATCH_PARENT_LOCATION_REMOTE_ID, self::MATCH_QUERY_TYPE, self::MATCH_SECTION,
        self::MATCH_SUBTREE, self::MATCH_VISIBILITY,
        // aliases
        'content_type',
        // BC
        'contenttype_id', 'contenttype_identifier',
        // content-only
        self::MATCH_RELATES_TO, self::MATCH_RELATED_FROM
    );
    protected $returns = 'Content';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param array $sort
     * @param int $offset
     * @param int $limit
     * @param bool $tolerateMisses
     * @return ContentCollection
     * @throws InvalidArgumentException
     * @throws InvalidMatchConditionsException
     * @throws InvalidSortConditionsException
     * @throws UnauthorizedException
     */
    public function match(array $conditions, array $sort = array(), $offset = 0, $limit = 0, $tolerateMisses = false)
    {
        return $this->matchContent($conditions, $sort, $offset, $limit, $tolerateMisses);
    }

    /**
     * @param array $conditions
     * @param array $sort
     * @param int $offset
     * @return Content
     * @throws InvalidArgumentException
     * @throws InvalidMatchResultsNumberException
     * @throws InvalidMatchConditionsException
     * @throws InvalidSortConditionsException
     * @throws UnauthorizedException
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
     * NB: does NOT throw if no contents are matching...
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param array $sort
     * @param int $offset
     * @param int $limit
     * @param bool $tolerateMisses
     * @return ContentCollection
     * @throws InvalidArgumentException
     * @throws InvalidMatchConditionsException
     * @throws InvalidSortConditionsException
     * @throws UnauthorizedException
     */
    public function matchContent(array $conditions, array $sort = array(), $offset = 0, $limit = 0, $tolerateMisses = false)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            switch ($key) {

                case self::MATCH_RELATES_TO:
                    $contentService = $this->repository->getContentService();
                    $contents = array();
                    // allow to specify the objects to relate to using different ways
                    $relatedContents = $this->match($values, $tolerateMisses);
                    foreach ($relatedContents as $relatedContent) {
                        foreach ($contentService->loadReverseRelations($relatedContent->contentInfo) as $relatingContent) {
                            $contents[$relatingContent->contentInfo->id] = $relatingContent;
                        }
                    }
                    break;

                case self::MATCH_RELATED_FROM:
                    $contentService = $this->repository->getContentService();
                    $contents = array();
                    // allow to specify the objects we relate to using different ways
                    $relatingContents = $this->match($values, $tolerateMisses);
                    foreach ($relatingContents as $relatingContent) {
                        foreach ($contentService->loadRelations($relatingContent->contentInfo) as $relatedContent) {
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

                    if ($key == self::MATCH_QUERY_TYPE) {
                        $query = $this->getQueryByQueryType($values);
                    } else {
                        $query = new Query();
                        $query->filter = $this->getQueryCriterion($key, $values);
                    }

                    // q: when getting a query via QueryType, should we always inject offset/limit?
                    $query->limit = $limit != 0 ? $limit : $this->queryLimit;
                    $query->offset = $offset;
                    if (isset($query->performCount)) $query->performCount = false;
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
                                // NB: assignment instead of comparison is correct
                                if ($sortClauses = $this->getDefaultSortClauses()) {
                                    $query->sortClauses = $sortClauses;
                                }
                        }
                    }

                    $results = $this->getSearchService()->findContent($query);

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
        $query->limit = $this->queryLimit;
        if (isset($query->performCount)) $query->performCount = false;
        $query->filter = new Query\Criterion\ParentLocationId($parentLocationIds);
        $results = $this->getSearchService()->findContent($query);

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
        $query->limit = $this->queryLimit;
        if (isset($query->performCount)) $query->performCount = false;
        $query->filter = new Query\Criterion\ContentTypeIdentifier($contentTypeIdentifiers);
        // sort objects by depth, lower to higher, so that deleting them has less chances of failure
        // NB: we only do this in eZP versions that allow depth sorting on content queries
        if ($sortClauses = $this->getDefaultSortClauses()) {
            $query->sortClauses = $sortClauses;
        }

        $results = $this->getSearchService()->findContent($query);

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
        $query->limit = $this->queryLimit;
        if (isset($query->performCount)) $query->performCount = false;
        $query->filter = new Query\Criterion\ContentTypeId($contentTypeIds);
        // sort objects by depth, lower to higher, so that deleting them has less chances of failure
        // NB: we only do this in eZP versions that allow depth sorting on content queries
        if ($sortClauses = $this->getDefaultSortClauses()) {
            $query->sortClauses = $sortClauses;
        }
        $results = $this->getSearchService()->findContent($query);

        $contents = [];
        foreach ($results->searchHits as $result) {
            // make sure we return every object only once
            $contents[$result->valueObject->contentInfo->id] = $result->valueObject;
        }

        return $contents;
    }

    /**
     * @return false|\eZ\Publish\API\Repository\Values\Content\Query\SortClause[]
     */
    protected function getDefaultSortClauses()
    {
        if (class_exists('eZ\Publish\API\Repository\Values\Content\Query\SortClause\LocationDepth')) {
            // Work around the fact that, on eZP 5.4 with recent ezplatform-solr-search-engine versions, sort class
            // LocationDepth does exist, but it is not supported by the Solr-based search engine.
            // The best workaround that we found so far: test a dummy query!
            $searchService = $this->getSearchService();
            if (class_exists('EzSystems\EzPlatformSolrSearchEngine\Handler') /*&& $searchService instanceof */) {
                try {
                    $query = new Query();
                    $query->limit = 1;
                    $query->performCount = false;
                    $query->filter = new Query\Criterion\ContentTypeIdentifier('this_is_a_very_unlikely_content_type_identifier');
                    //$query->filter = new Query\Criterion\ContentTypeId($contentTypeIds);
                } catch (NotImplementedException $e) {
                    return false;
                }
            }

            return array(new Query\SortClause\LocationDepth(Query::SORT_DESC));
        }

        return false;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\Core\QueryType\QueryTypeRegistry;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidSortConditionsException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use PhpParser\Node\Expr\Isset_;

/**
 * @todo extend to allow matching by modifier, language code, content_type_group_id
 */
abstract class QueryBasedMatcher extends RepositoryMatcher
{
    const MATCH_CONTENT_ID = 'content_id';
    const MATCH_LOCATION_ID = 'location_id';
    const MATCH_CONTENT_REMOTE_ID = 'content_remote_id';
    const MATCH_LOCATION_REMOTE_ID = 'location_remote_id';
    const MATCH_ATTRIBUTE = 'attribute';
    const MATCH_CONTENT_TYPE_ID = 'content_type_id';
    const MATCH_CONTENT_TYPE_IDENTIFIER = 'content_type_identifier';
    const MATCH_CREATION_DATE = 'creation_date';
    const MATCH_GROUP = 'group';
    const MATCH_LANGUAGE_CODE = 'lang';
    const MATCH_MODIFICATION_DATE = 'modification_date';
    const MATCH_OBJECT_STATE = 'object_state';
    const MATCH_OWNER = 'owner';
    const MATCH_PARENT_LOCATION_ID = 'parent_location_id';
    const MATCH_PARENT_LOCATION_REMOTE_ID = 'parent_location_remote_id';
    const MATCH_QUERY_TYPE = 'query_type';
    const MATCH_SECTION = 'section';
    const MATCH_SUBTREE = 'subtree';
    const MATCH_VISIBILITY = 'visibility';

    const SORT_CONTENT_ID = 'content_id';
    const SORT_CONTENT_NAME = 'name';
    const SORT_DATE_MODIFIED = 'modified';
    const SORT_DATE_PUBLISHED = 'published';
    const SORT_LOCATION_DEPTH = 'depth';
    const SORT_LOCATION_ID = 'node_id';
    const SORT_LOCATION_ISMAIN = 'is_main';
    const SORT_LOCATION_PATH = 'path';
    const SORT_LOCATION_PRIORITY = 'priority';
    const SORT_LOCATION_VISIBILITY = 'visibility';
    const SORT_SECTION_IDENTIFIER = 'section_identifier';
    const SORT_SECTION_NAME = 'section_name';

    // useful f.e. when talking to Solr, which defaults to java integers for max nr of items for queries
    const INT_MAX_16BIT = 2147483647;

    static protected $operatorsMap = array(
        'eq' => Operator::EQ,
        'gt' => Operator::GT,
        'gte' => Operator::GTE,
        'lt' => Operator::LT,
        'lte' => Operator::LTE,
        'in' => Operator::IN,
        'between' => Operator::BETWEEN,
        'like' => Operator::LIKE,
        'contains' => Operator::CONTAINS,
        Operator::EQ => Operator::EQ,
        Operator::GT => Operator::GT,
        Operator::GTE => Operator::GTE,
        Operator::LT => Operator::LT,
        Operator::LTE => Operator::LTE,
    );

    /** @var  KeyMatcherInterface $groupMatcher */
    protected $groupMatcher;
    /** @var  KeyMatcherInterface $sectionMatcher */
    protected $sectionMatcher;
    /** @var  KeyMatcherInterface $stateMatcher */
    protected $stateMatcher;
    /** @var  KeyMatcherInterface $userMatcher */
    protected $userMatcher;
    /** @var int $queryLimit */
    protected $queryLimit;
    /** @var QueryTypeRegistry|null */
    protected $queryTypeRegistry;

    /**
     * @param Repository $repository
     * @param KeyMatcherInterface $groupMatcher
     * @param KeyMatcherInterface $sectionMatcher
     * @param KeyMatcherInterface $stateMatcher
     * @param KeyMatcherInterface $userMatcher
     * @param int $queryLimit passed to the repo as max. number of results to fetch. Important to avoid SOLR errors
     * @todo inject the services needed, not the whole repository
     */
    public function __construct(Repository $repository, KeyMatcherInterface $groupMatcher = null,
        KeyMatcherInterface $sectionMatcher = null, KeyMatcherInterface $stateMatcher = null,
        KeyMatcherInterface $userMatcher = null, $queryLimit = null, $queryTypeRegistry = null)
    {
        parent::__construct($repository);
        $this->groupMatcher = $groupMatcher;
        $this->sectionMatcher = $sectionMatcher;
        $this->stateMatcher = $stateMatcher;
        $this->userMatcher = $userMatcher;
        $this->queryTypeRegistry = $queryTypeRegistry;

        if ($queryLimit !== null) {
            $this->queryLimit = (int)$queryLimit;
        } else {
            $this->queryLimit = self::INT_MAX_16BIT;
        }
    }

    /**
     * @param $key
     * @param $values
     * @return mixed should it be \eZ\Publish\API\Repository\Values\Content\Query\CriterionInterface ?
     * @throws InvalidMatchConditionsException for unsupported keys
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
                // NB: seems to cause problems with EZP 2014.3
                return new Query\Criterion\LocationId(reset($values));

            case self::MATCH_CONTENT_REMOTE_ID:
                return new Query\Criterion\RemoteId($values);

            case self::MATCH_LOCATION_REMOTE_ID:
                return new Query\Criterion\LocationRemoteId($values);

            case self::MATCH_ATTRIBUTE:
                $spec = reset($values);
                $attribute = key($values);
                $match = reset($spec);
                $operator = key($spec);
                if (!isset(self::$operatorsMap[$operator])) {
                    throw new InvalidMatchConditionsException("Can not use '$operator' as comparison operator for attributes");
                }
                return new Query\Criterion\Field($attribute, self::$operatorsMap[$operator], $match);

            case 'contenttype_id':
            case self::MATCH_CONTENT_TYPE_ID:
                return new Query\Criterion\ContentTypeId($values);

            case 'contenttype_identifier':
            case self::MATCH_CONTENT_TYPE_IDENTIFIER:
                return new Query\Criterion\ContentTypeIdentifier($values);

            case self::MATCH_CREATION_DATE:
                $match = reset($values);
                $operator = key($values);
                if (!isset(self::$operatorsMap[$operator])) {
                    throw new InvalidMatchConditionsException("Can not use '$operator' as comparison operator for dates");
                }
                return new Query\Criterion\DateMetadata(Query\Criterion\DateMetadata::CREATED, self::$operatorsMap[$operator], $match);

            case self::MATCH_GROUP:
                foreach($values as &$value) {
                    if (!ctype_digit($value)) {
                        $value = $this->groupMatcher->matchOneByKey($value)->id;
                    }
                }
                return new Query\Criterion\UserMetadata(Query\Criterion\UserMetadata::GROUP, Operator::IN, $values);

            case self::MATCH_LANGUAGE_CODE:
                return new Query\Criterion\LanguageCode($values);

            case self::MATCH_MODIFICATION_DATE:
                $match = reset($values);
                $operator = key($values);
                if (!isset(self::$operatorsMap[$operator])) {
                    throw new InvalidMatchConditionsException("Can not use '$operator' as comparison operator for dates");
                }
                return new Query\Criterion\DateMetadata(Query\Criterion\DateMetadata::MODIFIED, self::$operatorsMap[$operator], $match);

            case self::MATCH_OBJECT_STATE:
                foreach($values as &$value) {
                    if (!ctype_digit($value)) {
                        $value = $this->stateMatcher->matchOneByKey($value)->id;
                    }
                }
                return new Query\Criterion\ObjectStateId($values);

            case self::MATCH_OWNER:
                foreach($values as &$value) {
                    if (!ctype_digit($value)) {
                        $value = $this->userMatcher->matchOneByKey($value)->id;
                    }
                }
                return new Query\Criterion\UserMetadata(Query\Criterion\UserMetadata::OWNER, Operator::IN, $values);

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

            case self::MATCH_SECTION:
                foreach($values as &$value) {
                    if (!ctype_digit($value)) {
                        $value = $this->sectionMatcher->matchOneByKey($value)->id;
                    }
                }
                return new Query\Criterion\SectionId($values);

            case self::MATCH_SUBTREE:
                return new Query\Criterion\Subtree($values);

            case self::MATCH_VISIBILITY:
                /// @todo error/warning if there is more than 1 value...
                $value = reset($values);
                if ($value) {
                    return new Query\Criterion\Visibility(Query\Criterion\Visibility::VISIBLE);
                } else {
                    return new Query\Criterion\Visibility(Query\Criterion\Visibility::HIDDEN);
                }

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
                $value = reset($values);
                $subCriterion = $this->getQueryCriterion(key($values), $value);
                return new Query\Criterion\LogicalNot($subCriterion);

            case self::MATCH_QUERY_TYPE:
                throw new InvalidMatchConditionsException($this->returns . " can not use a QueryType as sub-condition");

            default:
                throw new InvalidMatchConditionsException($this->returns . " can not be matched because matching condition '$key' is not supported. Supported conditions are: " .
                    implode(', ', $this->allowedConditions));
        }
    }

    /**
     * @param array $sortDefinition
     * @return array
     * @throws InvalidSortConditionsException
     */
    protected function getSortClauses(array $sortDefinition)
    {
        $out = array();

        foreach ($sortDefinition as $sortItem) {

            if (is_string($sortItem)) {
                $sortItem = array('sort_field' => $sortItem);
            }
            if (!is_array($sortItem) || !isset($sortItem['sort_field'])) {
                throw new InvalidSortConditionsException("Missing sort_field element in sorting definition");
            }
            if (!isset($sortItem['sort_order'])) {
                // we have to pick a default ;-)
                $sortItem['sort_order'] = 'ASC';
            }

            $direction = $this->hash2SortOrder($sortItem['sort_order']);

            switch($sortItem['sort_field']) {
                case self::SORT_CONTENT_ID:
                    $out[] = new SortClause\ContentId($direction);
                    break;
                case self::SORT_CONTENT_NAME:
                    $out[] = new SortClause\ContentName($direction);
                    break;
                case self::SORT_DATE_MODIFIED:
                    $out[] = new SortClause\DateModified($direction);
                    break;
                case self::SORT_DATE_PUBLISHED:
                    $out[] = new SortClause\DatePublished($direction);
                    break;
                /// @todo
                //case self::SORT_FIELD:
                //    $out[] = new SortClause\Field($direction);
                //    break;
                case self::SORT_LOCATION_DEPTH:
                    $out[] = new SortClause\Location\Depth($direction);
                    break;
                case self::SORT_LOCATION_ID:
                    $out[] = new SortClause\Location\Id($direction);
                    break;
                case self::SORT_LOCATION_ISMAIN:
                    $out[] = new SortClause\Location\IsMainLocation($direction);
                    break;
                case self::SORT_LOCATION_PATH:
                    $out[] = new SortClause\Location\Path($direction);
                    break;
                case self::SORT_LOCATION_PRIORITY:
                    $out[] = new SortClause\Location\Priority($direction);
                    break;
                case self::SORT_LOCATION_VISIBILITY:
                    $out[] = new SortClause\Location\Visibility($direction);
                    break;
                case self::SORT_SECTION_IDENTIFIER:
                    $out[] = new SortClause\SectionIdentifier($direction);
                    break;
                case self::SORT_SECTION_NAME:
                    $out[] = new SortClause\SectionName($direction);
                    break;
                default:
                    throw new InvalidSortConditionsException("Sort field '{$sortItem['sort_field']}' not implemented");
            }
        }

        return $out;
    }

    /**
     * @param $queryTypeDef
     * @return Query
     * @throws InvalidMatchConditionsException
     */
    protected function getQueryByQueryType($queryTypeDef)
    {
        if ($this->queryTypeRegistry == null) {
            throw new InvalidMatchConditionsException('Matching by query_type is not supported with this eZP version');
        }
        if (is_string($queryTypeDef)) {
            $queryTypeDef = array('name' => $queryTypeDef);
        }
        if (!isset($queryTypeDef['name'])) {
            throw new InvalidMatchConditionsException("Matching by query_type is not supported without 'name'");
        }

        $qt = $this->queryTypeRegistry->getQueryType($queryTypeDef['name']);
        $q = $qt->getQuery(isset($queryTypeDef['parameters']) ? $queryTypeDef['parameters'] : array());
        return $q;
    }

    /**
     * @todo investigate how to better return the 'legacy' (db based) search engine even when a Solr-based one is available
     * @return \eZ\Publish\API\Repository\SearchService
     */
    protected function getSearchService()
    {
        return $this->repository->getSearchService();
    }

    protected function hash2SortOrder($value)
    {
        $sortOrder = null;

        if ($value !== null) {
            if (strtoupper($value) === 'ASC') {
                $sortOrder = Query::SORT_ASC;
            } else {
                $sortOrder = Query::SORT_DESC;
            }
        }

        return $sortOrder;
    }

    /**
     * @param int $value
     * @return string
     */
    protected function sortOrder2Hash($value)
    {
        if ($value === Query::SORT_ASC) {
            return 'ASC';
        } else {
            return 'DESC';
        }
    }
}

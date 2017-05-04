<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Repository;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;

/**
 * @todo extend to allow matching by creator, modifier, owner, language code, content_type_group_id...
 */
abstract class QueryBasedMatcher extends RepositoryMatcher
{
    const MATCH_CONTENT_ID = 'content_id';
    const MATCH_LOCATION_ID = 'location_id';
    const MATCH_CONTENT_REMOTE_ID = 'content_remote_id';
    const MATCH_LOCATION_REMOTE_ID = 'location_remote_id';
    const MATCH_ATTRIBUTE = 'attribute';
    const MATCH_CONTENT_TYPE_ID = 'contenttype_id';
    const MATCH_CONTENT_TYPE_IDENTIFIER = 'contenttype_identifier';
    const MATCH_CREATION_DATE = 'creation_date';
    const MATCH_GROUP = 'group';
    const MATCH_MODIFICATION_DATE = 'modification_date';
    const MATCH_OBJECT_STATE = 'object_state';
    const MATCH_OWNER = 'owner';
    const MATCH_PARENT_LOCATION_ID = 'parent_location_id';
    const MATCH_PARENT_LOCATION_REMOTE_ID = 'parent_location_remote_id';
    const MATCH_SECTION = 'section';
    const MATCH_SUBTREE = 'subtree';
    const MATCH_VISIBILITY = 'visibility';

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

    protected $groupMatcher;
    protected $sectionMatcher;
    protected $stateMatcher;
    protected $userMatcher;

    /**
     * @param Repository $repository
     * @param KeyMatcherInterface $groupMatcher
     * @param KeyMatcherInterface $sectionMatcher
     * @param KeyMatcherInterface $stateMatcher
     * @param KeyMatcherInterface $userMatcher
     * @todo inject the services needed, not the whole repository
     */
    public function __construct(Repository $repository, KeyMatcherInterface $groupMatcher = null,
        KeyMatcherInterface $sectionMatcher = null, KeyMatcherInterface $stateMatcher = null,
        KeyMatcherInterface $userMatcher = null)
    {
        parent::__construct($repository);
        $this->userMatcher = $userMatcher;
        $this->sectionMatcher = $sectionMatcher;
        $this->stateMatcher = $stateMatcher;
        $this->userMatcher = $userMatcher;
    }

    /**
     * @param $key
     * @param $values
     * @return mixed should it be \eZ\Publish\API\Repository\Values\Content\Query\CriterionInterface ?
     * @throws \Exception for unsupported keys
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
                return new Query\Criterion\LocationId($values);

            case self::MATCH_CONTENT_REMOTE_ID:
                return new Query\Criterion\RemoteId(reset($values));

            case self::MATCH_LOCATION_REMOTE_ID:
                return new Query\Criterion\LocationRemoteId($values);

            case self::MATCH_ATTRIBUTE:
                $spec = reset($values);
                $attribute = key($values);
                $match = reset($spec);
                $operator = key($spec);
                if (!isset(self::$operatorsMap[$operator])) {
                    throw new \Exception("Can not use '$operator' as comparison operator for attributes");
                }
                return new Query\Criterion\Field($attribute, self::$operatorsMap[$operator], $match);

            case 'content_type_id':
            case self::MATCH_CONTENT_TYPE_ID:
                return new Query\Criterion\ContentTypeId($values);

            case 'content_type_identifier':
            case self::MATCH_CONTENT_TYPE_IDENTIFIER:
                return new Query\Criterion\ContentTypeIdentifier($values);

            case self::MATCH_CREATION_DATE:
                $match = reset($values);
                $operator = key($values);
                if (!isset(self::$operatorsMap[$operator])) {
                    throw new \Exception("Can not use '$operator' as comparison operator for dates");
                }
                return new Query\Criterion\DateMetadata(Query\Criterion\DateMetadata::CREATED, self::$operatorsMap[$operator], $match);

            case self::MATCH_GROUP:
                foreach($values as &$value) {
                    if (!ctype_digit($value)) {
                        $value = $this->groupMatcher->matchOneByKey($value)->id;
                    }
                }
                return new Query\Criterion\UserMetadata(Query\Criterion\UserMetadata::GROUP, Operator::IN, $values);

            case self::MATCH_MODIFICATION_DATE:
                $match = reset($values);
                $operator = key($values);
                if (!isset(self::$operatorsMap[$operator])) {
                    throw new \Exception("Can not use '$operator' as comparison operator for dates");
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

            default:
                throw new \Exception($this->returns . " can not be matched because matching condition '$key' is not supported. Supported conditions are: " .
                    implode(', ', $this->allowedConditions));
        }
    }
}

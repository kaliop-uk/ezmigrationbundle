<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\Content\Query;

/**
 */
abstract class QueryBasedMatcher extends RepositoryMatcher
{
    const MATCH_CONTENT_ID = 'content_id';
    const MATCH_LOCATION_ID = 'location_id';
    const MATCH_CONTENT_REMOTE_ID = 'content_remote_id';
    const MATCH_LOCATION_REMOTE_ID = 'location_remote_id';
    const MATCH_PARENT_LOCATION_ID = 'parent_location_id';
    const MATCH_PARENT_LOCATION_REMOTE_ID = 'parent_location_remote_id';
    const MATCH_CONTENT_TYPE_ID = 'contenttype_id';
    const MATCH_CONTENT_TYPE_IDENTIFIER = 'contenttype_identifier';

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

            case 'content_type_id':
            case self::MATCH_CONTENT_TYPE_ID:
                return new Query\Criterion\ContentTypeId($values);

            case 'content_type_identifier':
            case self::MATCH_CONTENT_TYPE_IDENTIFIER:
                return new Query\Criterion\ContentTypeIdentifier($values);

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
                $subCriterion = reset($values);
                $value = reset($subCriterion);
                $subCriterion = $this->getQueryCriterion(key($subCriterion), $value);
                return new Query\Criterion\LogicalNot($subCriterion);

            default:
                throw new \Exception($this->returns . " can not be matched because matching condition '$key' is not supported. Supported conditions are: " .
                    implode(', ', $this->allowedConditions));
        }
    }
}

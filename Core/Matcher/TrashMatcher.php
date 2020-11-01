<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\Content\Query;
use Kaliop\eZMigrationBundle\API\Collection\TrashedItemCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;

/// q: is it better to extend Content or Location Matcher ?
class TrashMatcher extends ContentMatcher
{
    const MATCH_ITEM_ID = 'item_id';

    protected $allowedConditions = array(
        self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_CONTENT_ID, self::MATCH_LOCATION_ID, self::MATCH_CONTENT_REMOTE_ID, self::MATCH_LOCATION_REMOTE_ID,
        self::MATCH_ATTRIBUTE, self::MATCH_CONTENT_TYPE_ID, self::MATCH_CONTENT_TYPE_IDENTIFIER, self::MATCH_GROUP,
        self::MATCH_CREATION_DATE, self::MATCH_MODIFICATION_DATE, self::MATCH_OBJECT_STATE, self::MATCH_OWNER,
        self::MATCH_PARENT_LOCATION_ID, self::MATCH_PARENT_LOCATION_REMOTE_ID, self::MATCH_SECTION, self::MATCH_SUBTREE,
        self::MATCH_VISIBILITY,
        // aliases
        'content_type', 'content_type_id', 'content_type_identifier',
    );

    protected $returns = 'Trashed-Item';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param array $sort
     * @param int $offset
     * @param int $limit
     * @param bool $tolerateMisses
     * @return TrashedItemCollection
     * @throws InvalidMatchConditionsException
     */
    public function match(array $conditions, array $sort = array(), $offset = 0, $limit = 0, $tolerateMisses = false)
    {
        /// @todo throw if we get passed sorting or offset

        return $this->matchItem($conditions, $tolerateMisses);
    }

    /**
     * @param array $conditions
     * @return TrashedItemCollection
     * @throws InvalidMatchConditionsException
     *
     * @todo test all supported matching conditions
     * @todo support matching by item_id
     * @todo test if sorting and offset,limit do work
     */
    public function matchItem(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            $query = new Query();
            $query->limit = $this->queryLimit;
            if (isset($query->performCount)) $query->performCount = false;
            $query->filter = $this->getQueryCriterion($key, $values);
            $results = $this->repository->getTrashService()->findTrashItems($query);

            $items = [];
            foreach ($results->items as $result) {
                $items[$result->id] = $result;
            }

            return new TrashedItemCollection($items);
        }
    }
}

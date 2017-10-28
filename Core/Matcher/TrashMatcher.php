<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use Kaliop\eZMigrationBundle\API\Collection\TrashedItemCollection;
use \eZ\Publish\API\Repository\Values\Content\Query;

/// q: is it better to extends Content or Location Matcher ?
class TrashMatcher extends ContentMatcher
{
    const MATCH_ITEM_ID = 'item_id';

    protected $returns = 'Trashed-Item';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return TrashedItemCollection
     */
    public function match(array $conditions)
    {
        return $this->matchItem($conditions);
    }

    /**
     * @param array $conditions
     * @return TrashedItemCollection
     *
     * @todo test all supported matching conditions
     * @todo support matching by item_id
     */
    public function matchItem(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            $query = new Query();
            $query->limit = self::INT_MAX_16BIT;
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

<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use Kaliop\eZMigrationBundle\API\Collection\ObjectStateGroupCollection;
use \eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroup;

class ObjectStateGroupMatcher extends AbstractMatcher
{
    const MATCH_OBJECTSTATEGROUP_ID = 'objectstategroup_id';

    protected $allowedConditions = array(
        self::MATCH_OBJECTSTATEGROUP_ID,
        // aliases
        'id',
    );
    protected $returns = 'ObjectStateGroup';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     *
     * @return ObjectStateGroupCollection
     */
    public function match(array $conditions)
    {
        $this->matchObjectStateGroup($conditions);
    }

    /**
     * @param int $groupId
     *
     * @return ObjectStateGroup
     */
    public function matchByKey($groupId)
    {
        return $this->findObjectStateGroupsById([$groupId])[$groupId];
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     *
     * @return ObjectStateGroupCollection
     */
    public function matchObjectStateGroup($conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_OBJECTSTATEGROUP_ID:
                    return new ObjectStateGroupCollection($this->findObjectStateGroupsById($values));

            }
        }
    }

    /**
     * @param int[] $objectStateGroupIds
     *
     * @return ObjectStateGroup[]
     */
    protected function findObjectStateGroupsById(array $objectStateGroupIds)
    {
        $objectStateGroups = [];

        foreach ($objectStateGroupIds as $objectStateGroupId) {
            // return unique contents
            $objectStateGroup = $this->repository->getObjectStateService()->loadObjectStateGroup($objectStateGroupId);
            $objectStateGroups[$objectStateGroup->id] = $objectStateGroup;
        }

        return $objectStateGroups;
    }
}

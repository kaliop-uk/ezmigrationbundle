<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use Kaliop\eZMigrationBundle\API\Collection\ObjectStateGroupCollection;
use \eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroup;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;

class ObjectStateGroupMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_OBJECTSTATEGROUP_ID = 'objectstategroup_id';
    const MATCH_OBJECTSTATEGROUP_IDENTIFIER = 'objectstategroup_identifier';

    protected $allowedConditions = array(
        self:: MATCH_ALL, self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_OBJECTSTATEGROUP_ID, self::MATCH_OBJECTSTATEGROUP_IDENTIFIER,
        // aliases
        'id', 'identifier'
    );
    protected $returns = 'ObjectStateGroup';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ObjectStateGroupCollection
     * @throws InvalidMatchConditionsException
     */
    public function match(array $conditions)
    {
        return $this->matchObjectStateGroup($conditions);
    }

    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_OBJECTSTATEGROUP_ID => $key);
        }
        return array(self::MATCH_OBJECTSTATEGROUP_IDENTIFIER => $key);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ObjectStateGroupCollection
     * @throws InvalidMatchConditionsException
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

                case 'identifier':
                case self::MATCH_OBJECTSTATEGROUP_IDENTIFIER:
                    return new ObjectStateGroupCollection($this->findObjectStateGroupsByIdentifier($values));

                case self::MATCH_ALL:
                    return new ObjectStateGroupCollection($this->findAllObjectStateGroups());

                case self::MATCH_AND:
                    return $this->matchAnd($values);

                case self::MATCH_OR:
                    return $this->matchOr($values);

                case self::MATCH_NOT:
                    return new ObjectStateGroupCollection(array_diff_key($this->findAllObjectStateGroups(), $this->matchObjectStateGroup($values)->getArrayCopy()));
            }
        }
    }

    /**
     * @param int[] $objectStateGroupIds
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

        /**
         * @param int[] $objectStateGroupIdentifiers
         * @return ObjectStateGroup[]
         */
    protected function findObjectStateGroupsByIdentifier(array $objectStateGroupIdentifiers)
    {
        $objectStateGroups = [];

        $groupsByIdentifier = $this->loadAvailableStateGroups();

        foreach ($objectStateGroupIdentifiers as $objectStateGroupIdentifier) {
            if (!array_key_exists($objectStateGroupIdentifier, $groupsByIdentifier)) {
                throw new NotFoundException("ObjectStateGroup", $objectStateGroupIdentifier);
            }
            // return unique contents
            $objectStateGroups[$groupsByIdentifier[$objectStateGroupIdentifier]->id] = $groupsByIdentifier[$objectStateGroupIdentifier];
        }

        return $objectStateGroups;
    }

    /**
     * @return ObjectStateGroup[] key: the group identifier
     */
    protected function findAllObjectStateGroups()
    {
        return $this->loadAvailableStateGroups();
    }

    /**
     * @return ObjectStateGroup[] key: the group identifier
     */
    protected function loadAvailableStateGroups()
    {
        $stateGroupsList = [];
        $objectStateService = $this->repository->getObjectStateService();

        foreach ($objectStateService->loadObjectStateGroups() as $group) {
            $stateGroupsList[$group->identifier] = $group;
        }

        return $stateGroupsList;
    }
}

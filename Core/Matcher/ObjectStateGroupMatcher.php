<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroup;
use eZ\Publish\Core\Base\Exceptions\NotFoundException as CoreNotFoundException;
use Kaliop\eZMigrationBundle\API\Collection\ObjectStateGroupCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

class ObjectStateGroupMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_OBJECTSTATEGROUP_ID = 'object_state_group_id';
    const MATCH_OBJECTSTATEGROUP_IDENTIFIER = 'object_state_group_identifier';

    protected $allowedConditions = array(
        self:: MATCH_ALL, self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_OBJECTSTATEGROUP_ID, self::MATCH_OBJECTSTATEGROUP_IDENTIFIER,
        // aliases
        'id', 'identifier',
        // BC
        'objectstategroup_id', 'objectstategroup_identifier',
    );
    protected $returns = 'ObjectStateGroup';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return ObjectStateGroupCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function match(array $conditions, $tolerateMisses = false)
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
     * @param bool $tolerateMisses
     * @return ObjectStateGroupCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function matchObjectStateGroup($conditions, $tolerateMisses = false)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case 'objectstategroup_id':
                case self::MATCH_OBJECTSTATEGROUP_ID:
                    return new ObjectStateGroupCollection($this->findObjectStateGroupsById($values, $tolerateMisses));

                case 'identifier':
                case 'objectstategroup_identifier':
                case self::MATCH_OBJECTSTATEGROUP_IDENTIFIER:
                    return new ObjectStateGroupCollection($this->findObjectStateGroupsByIdentifier($values, $tolerateMisses));

                case self::MATCH_ALL:
                    return new ObjectStateGroupCollection($this->findAllObjectStateGroups());

                case self::MATCH_AND:
                    return $this->matchAnd($values, $tolerateMisses);

                case self::MATCH_OR:
                    return $this->matchOr($values, $tolerateMisses);

                case self::MATCH_NOT:
                    return new ObjectStateGroupCollection(array_diff_key($this->findAllObjectStateGroups(), $this->matchObjectStateGroup($values, true)->getArrayCopy()));
            }
        }
    }

    /**
     * @param int[] $objectStateGroupIds
     * @param bool $tolerateMisses
     * @return ObjectStateGroup[]
     * @throws NotFoundException
     */
    protected function findObjectStateGroupsById(array $objectStateGroupIds, $tolerateMisses = false)
    {
        $objectStateGroups = [];

        foreach ($objectStateGroupIds as $objectStateGroupId) {
            try {
                // return unique contents
                $objectStateGroup = $this->repository->getObjectStateService()->loadObjectStateGroup($objectStateGroupId);
                $objectStateGroups[$objectStateGroup->id] = $objectStateGroup;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $objectStateGroups;
    }

        /**
         * @param int[] $objectStateGroupIdentifiers
         * @param bool $tolerateMisses
         * @return ObjectStateGroup[]
         * @throws NotFoundException
         */
    protected function findObjectStateGroupsByIdentifier(array $objectStateGroupIdentifiers, $tolerateMisses = false)
    {
        $objectStateGroups = [];

        $groupsByIdentifier = $this->loadAvailableStateGroups();

        foreach ($objectStateGroupIdentifiers as $objectStateGroupIdentifier) {
            if (!array_key_exists($objectStateGroupIdentifier, $groupsByIdentifier)) {
                if ($tolerateMisses) {
                    continue;
                }
                throw new CoreNotFoundException("ObjectStateGroup", $objectStateGroupIdentifier);
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

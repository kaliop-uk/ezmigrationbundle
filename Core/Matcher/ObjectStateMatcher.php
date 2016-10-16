<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use Kaliop\eZMigrationBundle\API\Collection\ObjectStateCollection;
use eZ\Publish\API\Repository\Values\ObjectState\ObjectState;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;

class ObjectStateMatcher extends AbstractMatcher
{
    const MATCH_OBJECTSTATE_ID = 'objectstate_id';
    const MATCH_OBJECTSTATE_IDENTIFIER = 'objectstate_identifier';

    protected $allowedConditions = array(
        self::MATCH_OBJECTSTATE_ID,
        self::MATCH_OBJECTSTATE_IDENTIFIER,
        // aliases
        'id', 'identifier'
    );
    protected $returns = 'ObjectState';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     *
     * @return ObjectStateCollection
     */
    public function match(array $conditions)
    {
        $this->matchObjectState($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     *
     * @return ObjectStateCollection
     */
    public function matchObjectState($conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_OBJECTSTATE_ID:
                    return new ObjectStateCollection($this->findObjectStatesById($values));
                case 'identifier':
                case self::MATCH_OBJECTSTATE_IDENTIFIER:
                    return new ObjectStateCollection($this->findObjectStatesByIdentifier($values));
            }
        }
    }

    /**
     * @param int[] $objectStateIds
     *
     * @return ObjectState[]
     */
    protected function findObjectStatesById(array $objectStateIds)
    {
        $objectStates = [];

        foreach ($objectStateIds as $objectStateId) {
            // return unique contents
            $objectState = $this->repository->getObjectStateService()->loadObjectState($objectStateId);
            $objectStates[$objectState->id] = $objectState;
        }

        return $objectStates;
    }

    /**
     * @param array $objectStateIdentifiers
     *
     * @return ObjectState[]
     */
    protected function findObjectStatesByIdentifier(array $objectStateIdentifiers)
    {
        $objectStates = [];

        $stateList = $this->loadAvailableStates();

        foreach ($objectStateIdentifiers as $objectStateIdentifier) {
            if (!array_key_exists($objectStateIdentifier, $stateList)) {
                throw new NotFoundException("ObjectState", $objectStateIdentifier);
            }

            $objectStates[$stateList[$objectStateIdentifier]->id] = $stateList[$objectStateIdentifier];
        }

        return $objectStates;
    }

    /**
     * @return ObjectState[]
     */
    private function loadAvailableStates()
    {
        $stateList = [];
        $objectStateService = $this->repository->getObjectStateService();

        $groups = $objectStateService->loadObjectStateGroups();
        foreach ($groups as $group) {
            $states = $objectStateService->loadObjectStates($group);
            foreach ($states as $state) {
                $stateList[$state->id] = $state;
            }
        }

        return $stateList;
    }
}

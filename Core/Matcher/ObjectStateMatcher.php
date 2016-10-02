<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\ObjectState\ObjectState;
use Kaliop\eZMigrationBundle\API\Collection\ObjectStateCollection;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;

class ObjectStateMatcher extends AbstractMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_STATE_ID = 'objectstate_id';
    const MATCH_STATE_IDENTIFIER = 'objectstate_identifier';

    protected $allowedConditions = array(
        self::MATCH_STATE_ID, self::MATCH_STATE_IDENTIFIER,
        // aliases
        'id', 'identifier'
    );
    protected $returns = 'ObjectState';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ObjectStateCollection
     */
    public function match(array $conditions)
    {
        return $this->matchObjectState($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return ObjectStateCollection
     */
    public function matchObjectState(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_STATE_ID:
                   return new ObjectStateCollection($this->findObjectStatesById($values));

                case 'identifier':
                case self::MATCH_STATE_IDENTIFIER:
                    return new ObjectStateCollection($this->findObjectStatesByIdentifier($values));
            }
        }
    }

    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_STATE_ID => $key);
        }
        return array(self::MATCH_STATE_IDENTIFIER => $key);
    }

    /**
     * @param int[] $stateIds
     * @return ObjectState[]
     */
    protected function findObjectStatesById(array $stateIds)
    {
        $states = [];

        foreach ($stateIds as $stateId) {
            // return unique contents
            $state = $this->repository->getObjectStateService()->loadObjectState($stateId);
            $states[$state->id] = $state;
        }

        return $states;
    }

    /**
     * @param string[] $stateIdentifiers
     * @return ObjectState[]
     * @throws NotFoundException
     */
    protected function findObjectStatesByIdentifier(array $stateIdentifiers)
    {
        // we have to build this list, as the ObjectStateService does not allow to load a State by identifier...
        $statesList = array();
        $groups = $this->repository->getObjectStateService()->loadObjectStateGroups();
        foreach ($groups as $group) {
            $groupStates = $this->repository->getObjectStateService()->loadObjectStates($group);
            foreach($groupStates as $groupState) {
                $statesList[$groupState->identifier] = $groupState;
            }
        }

        $states = [];

        foreach ($stateIdentifiers as $stateIdentifier) {
            if (!isset($statesList[$stateIdentifier])) {
                throw new NotFoundException("ObjectState", $stateIdentifier);
            }
            $states[$statesList[$stateIdentifier]->id] = $statesList[$stateIdentifier];
        }

        return $states;
    }
}

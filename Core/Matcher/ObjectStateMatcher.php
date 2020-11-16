<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\ObjectState\ObjectState;
use eZ\Publish\Core\Base\Exceptions\NotFoundException as CoreNotFoundException;
use Kaliop\eZMigrationBundle\API\Collection\ObjectStateCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

class ObjectStateMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_OBJECTSTATE_ID = 'object_state_id';
    const MATCH_OBJECTSTATE_IDENTIFIER = 'object_state_identifier';

    protected $allowedConditions = array(
        self::MATCH_ALL, self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_OBJECTSTATE_ID, self::MATCH_OBJECTSTATE_IDENTIFIER,
        // aliases
        'id', 'identifier',
        // BC
        'objectstate_id', 'objectstate_identifier',
    );
    protected $returns = 'ObjectState';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return ObjectStateCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function match(array $conditions, $tolerateMisses = false)
    {
        return $this->matchObjectState($conditions, $tolerateMisses);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return ObjectStateCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function matchObjectState(array $conditions, $tolerateMisses = false)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case 'objectstate_id':
                case self::MATCH_OBJECTSTATE_ID:
                   return new ObjectStateCollection($this->findObjectStatesById($values, $tolerateMisses));

                case 'identifier':
                case 'objectstate_identifier':
                case self::MATCH_OBJECTSTATE_IDENTIFIER:
                    return new ObjectStateCollection($this->findObjectStatesByIdentifier($values, $tolerateMisses));

                case self::MATCH_ALL:
                    return new ObjectStateCollection($this->findAllObjectStates());

                case self::MATCH_AND:
                    return $this->matchAnd($values, $tolerateMisses);

                case self::MATCH_OR:
                    return $this->matchOr($values, $tolerateMisses);

                case self::MATCH_NOT:
                    return new ObjectStateCollection(array_diff_key($this->findAllObjectStates(), $this->matchObjectState($values, true)->getArrayCopy()));
            }
        }
    }

    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_OBJECTSTATE_ID => $key);
        }
        return array(self::MATCH_OBJECTSTATE_IDENTIFIER => $key);
    }

    /**
     * @param int[] $objectStateIds
     * @param bool $tolerateMisses
     * @return ObjectState[]
     * @throws NotFoundException
     */
    protected function findObjectStatesById(array $objectStateIds, $tolerateMisses = false)
    {
        $objectStates = [];

        foreach ($objectStateIds as $objectStateId) {
            try {
                // return unique contents
                $objectState = $this->repository->getObjectStateService()->loadObjectState($objectStateId);
                $objectStates[$objectState->id] = $objectState;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $objectStates;
    }

    /**
     * @param string[] $stateIdentifiers Accepts the state identifier if unique, otherwise "group-identifier/state-identifier"
     * @param bool $tolerateMisses
     * @return ObjectState[]
     * @throws NotFoundException
     */
    protected function findObjectStatesByIdentifier(array $stateIdentifiers, $tolerateMisses = false)
    {
        // we have to build this list, as the ObjectStateService does not allow to load a State by identifier...
        $statesList = $this->loadAvailableStates();

        $states = [];

        foreach ($stateIdentifiers as $stateIdentifier) {
            if (!isset($statesList[$stateIdentifier])) {
                if ($tolerateMisses) {
                    continue;
                }
                // a quick and dirty way of letting the user know that he/she might be using a non-unique identifier
                throw new CoreNotFoundException("ObjectState", $stateIdentifier . "' (either missing or non unique)");
            }
            $states[$statesList[$stateIdentifier]->id] = $statesList[$stateIdentifier];
        }

        return $states;
    }

    /**
     * @return ObjectState[] key: id
     */
    protected function findAllObjectStates()
    {
        $states = array();

        foreach ($this->loadAvailableStates() as $key => $state) {
            if (strpos($key, '/') !== false) {
                $states[$state->id] = $state;
            }
        }

        return $states;
    }

    /**
     * @return ObjectState[] key: the state identifier (for unique identifiers), group_identifier/state_identifier for all
     */
    protected function loadAvailableStates()
    {
        $statesList = array();
        $nonUniqueIdentifiers = array();

        $groups = $this->repository->getObjectStateService()->loadObjectStateGroups();
        foreach ($groups as $group) {
            $groupStates = $this->repository->getObjectStateService()->loadObjectStates($group);
            foreach ($groupStates as $groupState) {
                // we always add the states using 'group/state' identifiers
                $statesList[$group->identifier . '/' . $groupState->identifier] = $groupState;
                // we only add the state using plain identifier if it is unique
                if (isset($statesList[$groupState->identifier])) {
                    unset($statesList[$groupState->identifier]);
                    $nonUniqueIdentifiers[] = $groupState->identifier;
                } else {
                    if (!isset($nonUniqueIdentifiers[$groupState->identifier])) {
                        $statesList[$groupState->identifier] = $groupState;
                    }
                }
            }
        }

        return $statesList;
    }
}

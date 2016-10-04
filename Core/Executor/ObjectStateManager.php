<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateGroupMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateMatcher;
use Kaliop\eZMigrationBundle\API\Collection\ObjectStateCollection;

class ObjectStateManager extends RepositoryExecutor
{
    /**
     * @var array
     */
    protected $supportedStepTypes = array('object_state');

    /**
     * @var ObjectStateMatcher
     */
    private $objectStateMatcher;

    /**
     * @var ObjectStateGroupMatcher
     */
    private $objectStateGroupMatcher;

    /**
     * @param ObjectStateMatcher      $objectStateMatcher
     * @param ObjectStateGroupMatcher $objectStateGroupMatcher
     */
    public function __construct(ObjectStateMatcher $objectStateMatcher, ObjectStateGroupMatcher $objectStateGroupMatcher)
    {
        $this->objectStateMatcher = $objectStateMatcher;
        $this->objectStateGroupMatcher = $objectStateGroupMatcher;
    }

    /**
     * Handle the create step of object state migrations.
     *
     * @throws \Exception
     */
    protected function create()
    {
        foreach(array('object_state_group_id', 'names', 'identifier') as $key) {
            if (!isset($this->dsl[$key])) {
                throw new \Exception("The '$key' key is missing in a object state creation definition");
            }
        }

        $objectStateService = $this->repository->getObjectStateService();

        $objectStateGroupId = $this->dsl['object_state_group_id'];
        if ($this->referenceResolver->isReference($objectStateGroupId)) {
            $objectStateGroupId = $this->referenceResolver->getReferenceValue($objectStateGroupId);
        }

        $objectStateGroup = $this->objectStateGroupMatcher->matchByKey($objectStateGroupId);

        $objectStateCreateStruct = $objectStateService->newObjectStateCreateStruct($this->dsl['identifier']);
        $objectStateCreateStruct->defaultLanguageCode = self::DEFAULT_LANGUAGE_CODE;

        foreach ($this->dsl['names'] as $name) {
            if ( array_key_exists('languageCode', $name) && array_key_exists('name', $name)) {
                $objectStateCreateStruct->names[$name['languageCode']] = $name['name'];
            }
        }

        if (0 === count($objectStateCreateStruct->names)) {
            throw new \Exception('No object state names have been defined. Need to specify atleast one to create the state.');
        }

        $objectState = $objectStateService->createObjectState($objectStateGroup, $objectStateCreateStruct);

        $this->setReferences($objectState);
    }

    /**
     * Handle the update step of object state migrations.
     *
     * @throws \Exception
     */
    protected function update()
    {
        $stateCollection = $this->matchStates('update');

        $objectStateService = $this->repository->getObjectStateService();

        if (count($stateCollection) > 1 && array_key_exists('references', $this->dsl)) {
            throw new \Exception("Can not execute Object State update because multiple states match, and a references section is specified in the dsl. References can be set when only 1 state matches");
        }

        foreach ($stateCollection as $state) {
            $objectStateUpdateStruct = $objectStateService->newObjectStateUpdateStruct();

            foreach ($this->dsl['names'] as $name) {
                $objectStateUpdateStruct->names[$name['languageCode']] = $name['name'];
            }

            $state = $objectStateService->updateObjectState($state, $objectStateUpdateStruct);

            $this->setReferences($state);
        }

    }

    /**
     * Handle the deletion step of object state migrations.
     */
    protected function delete()
    {
        $stateCollection = $this->matchStates('delete');

        $objectStateService = $this->repository->getObjectStateService();

        foreach ($stateCollection as $state) {
            $objectStateService->deleteObjectState($state);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setReferences($objectState)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        foreach ($this->dsl['references'] as $reference) {
            switch ($reference['attribute']) {
                case 'object_state_id':
                case 'id':
                    $value = $objectState->id;
                    break;
                default:
                    throw new \InvalidArgumentException('Object State Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }

    /**
     * @param string $action
     *
     * @throws \Exception
     *
     * @return ObjectStateCollection
     */
    private function matchStates($action)
    {
        if (!isset($this->dsl['state_id']) && !isset($this->dsl['state_identifier']) && !isset($this->dsl['match'])) {
            throw new \Exception("The ID or Identifier of an object state or a Match Condition is required to $action an object state.");
        }

        if (!isset($this->dsl['match'])) {
            if (isset($this->dsl['id'])) {
                $this->dsl['match'] = array('objectstate_id' => $this->dsl['id']);
            } elseif (isset($this->dsl['identifier'])) {
                $this->dsl['match'] = array('objectstate_identifier' => $this->dsl['identifier']);
            }
        }

        return $this->objectStateMatcher->match(
            $this->convertMatchReferences($this->dsl['match'])
        );
    }

    /**
     * @param array $match
     *
     * @return array
     */
    private function convertMatchReferences($match)
    {
        foreach ($match as $condition => $values) {
            if (is_array($values)) {
                foreach ($values as $position => $value) {
                    if ($this->referenceResolver->isReference($value)) {
                        $match[$condition][$position] = $this->referenceResolver->getReferenceValue($value);
                    }
                }
            } else {
                if ($this->referenceResolver->isReference($values)) {
                    $match[$condition] = $this->referenceResolver->getReferenceValue($values);
                }
            }
        }

        return $match;
    }
}

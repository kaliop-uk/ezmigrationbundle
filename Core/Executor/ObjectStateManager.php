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
    protected $objectStateMatcher;

    /**
     * @var ObjectStateGroupMatcher
     */
    protected $objectStateGroupMatcher;

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
        foreach (array('object_state_group', 'names', 'identifier') as $key) {
            if (!isset($this->dsl[$key])) {
                throw new \Exception("The '$key' key is missing in a object state creation definition");
            }
        }

        if (!count($this->dsl['names'])) {
            throw new \Exception('No object state names have been defined. Need to specify at least one to create the state.');
        }

        $objectStateService = $this->repository->getObjectStateService();

        $objectStateGroupId = $this->dsl['object_state_group'];
        $objectStateGroupId = $this->referenceResolver->resolveReference($objectStateGroupId);
        $objectStateGroup = $this->objectStateGroupMatcher->matchOneByKey($objectStateGroupId);

        $objectStateCreateStruct = $objectStateService->newObjectStateCreateStruct($this->dsl['identifier']);
        $objectStateCreateStruct->defaultLanguageCode = self::DEFAULT_LANGUAGE_CODE;

        foreach ($this->dsl['names'] as $languageCode => $name) {
            $objectStateCreateStruct->names[$languageCode] = $name;
        }
        if (isset($this->dsl['descriptions'])) {
            foreach ($this->dsl['descriptions'] as $languageCode => $description) {
                $objectStateCreateStruct->descriptions[$languageCode] = $description;
            }
        }

        $objectState = $objectStateService->createObjectState($objectStateGroup, $objectStateCreateStruct);

        $this->setReferences($objectState);

        return $objectState;
    }

    /**
     * Handle the update step of object state migrations.
     *
     * @throws \Exception
     */
    protected function update()
    {
        $stateCollection = $this->matchObjectStates('update');

        if (count($stateCollection) > 1 && array_key_exists('references', $this->dsl)) {
            throw new \Exception("Can not execute Object State update because multiple states match, and a references section is specified in the dsl. References can be set when only 1 state matches");
        }

        if (count($stateCollection) > 1 && isset($this->dsl['identifier'])) {
            throw new \Exception("Can not execute Object State update because multiple states match, and an identifier is specified in the dsl.");
        }

        $objectStateService = $this->repository->getObjectStateService();

        foreach ($stateCollection as $state) {
            $objectStateUpdateStruct = $objectStateService->newObjectStateUpdateStruct();

            if (isset($this->dsl['identifier'])) {
                $objectStateUpdateStruct->identifier = $this->dsl['identifier'];
            }
            if (isset($this->dsl['names'])) {
                foreach ($this->dsl['names'] as $name) {
                    $objectStateUpdateStruct->names[$name['languageCode']] = $name['name'];
                }
            }
            if (isset($this->dsl['descriptions'])) {
                foreach ($this->dsl['descriptions'] as $languageCode => $description) {
                    $objectStateUpdateStruct->descriptions[$languageCode] = $description;
                }
            }
            $state = $objectStateService->updateObjectState($state, $objectStateUpdateStruct);

            $this->setReferences($state);
        }

        return $stateCollection;
    }

    /**
     * Handle the deletion step of object state migrations.
     */
    protected function delete()
    {
        $stateCollection = $this->matchObjectStates('delete');

        $objectStateService = $this->repository->getObjectStateService();

        foreach ($stateCollection as $state) {
            $objectStateService->deleteObjectState($state);
        }

        return $stateCollection;
    }

    /**
     * @param string $action
     * @return ObjectStateCollection
     * @throws \Exception
     */
    private function matchObjectStates($action)
    {
        if (!isset($this->dsl['match'])) {
            throw new \Exception("A match Condition is required to $action an object state.");
        }

        $match = $this->dsl['match'];

        // convert the references passed in the match
        foreach ($match as $condition => $values) {
            if (is_array($values)) {
                foreach ($values as $position => $value) {
                    $match[$condition][$position] = $this->referenceResolver->resolveReference($value);
                }
            } else {
                $match[$condition] = $this->referenceResolver->resolveReference($values);
            }
        }

        return $this->objectStateMatcher->match($match);
    }

    /**
     * {@inheritdoc}
     * @param \eZ\Publish\API\Repository\Values\ObjectState\ObjectState $objectState
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
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\ObjectState\ObjectState;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateGroupMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateMatcher;
use Kaliop\eZMigrationBundle\API\Collection\ObjectStateCollection;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\API\EnumerableMatcherInterface;

/**
 * Handles object-state migrations.
 */
class ObjectStateManager extends RepositoryExecutor implements MigrationGeneratorInterface, EnumerableMatcherInterface
{
    /**
     * @var array
     */
    protected $supportedStepTypes = array('object_state');
    protected $supportedActions = array('create', 'load', 'update', 'delete');

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
     * Handles the create step of object state migrations.
     *
     * @throws \Exception
     */
    protected function create($step)
    {
        foreach (array('object_state_group', 'names', 'identifier') as $key) {
            if (!isset($step->dsl[$key])) {
                throw new \Exception("The '$key' key is missing in a object state creation definition");
            }
        }

        if (!count($step->dsl['names'])) {
            throw new \Exception('No object state names have been defined. Need to specify at least one to create the state.');
        }

        $objectStateService = $this->repository->getObjectStateService();

        $objectStateGroupId = $step->dsl['object_state_group'];
        $objectStateGroupId = $this->referenceResolver->resolveReference($objectStateGroupId);
        $objectStateGroup = $this->objectStateGroupMatcher->matchOneByKey($objectStateGroupId);

        $objectStateIdentifier = $this->referenceResolver->resolveReference($step->dsl['identifier']);
        $objectStateCreateStruct = $objectStateService->newObjectStateCreateStruct($objectStateIdentifier);
        $objectStateCreateStruct->defaultLanguageCode = $this->getLanguageCode($step); // was: self::DEFAULT_LANGUAGE_CODE;

        foreach ($step->dsl['names'] as $languageCode => $name) {
            $objectStateCreateStruct->names[$languageCode] = $name;
        }
        if (isset($step->dsl['descriptions'])) {
            foreach ($step->dsl['descriptions'] as $languageCode => $description) {
                $objectStateCreateStruct->descriptions[$languageCode] = $description;
            }
        }

        $objectState = $objectStateService->createObjectState($objectStateGroup, $objectStateCreateStruct);

        $this->setReferences($objectState, $step);

        return $objectState;
    }

    protected function load($step)
    {
        $stateCollection = $this->matchObjectStates('load', $step);

        $this->setReferences($stateCollection, $step);

        return $stateCollection;
    }

    /**
     * Handles the update step of object state migrations.
     *
     * @throws \Exception
     */
    protected function update($step)
    {
        $stateCollection = $this->matchObjectStates('update', $step);

        if (count($stateCollection) > 1 && array_key_exists('references', $step->dsl)) {
            throw new \Exception("Can not execute Object State update because multiple states match, and a references section is specified in the dsl. References can be set when only 1 state matches");
        }

        if (count($stateCollection) > 1 && isset($step->dsl['identifier'])) {
            throw new \Exception("Can not execute Object State update because multiple states match, and an identifier is specified in the dsl.");
        }

        $objectStateService = $this->repository->getObjectStateService();

        foreach ($stateCollection as $state) {
            $objectStateUpdateStruct = $objectStateService->newObjectStateUpdateStruct();

            if (isset($step->dsl['identifier'])) {
                $objectStateUpdateStruct->identifier = $this->referenceResolver->resolveReference($step->dsl['identifier']);
            }
            if (isset($step->dsl['names'])) {
                foreach ($step->dsl['names'] as $name) {
                    $objectStateUpdateStruct->names[$name['languageCode']] = $name['name'];
                }
            }
            if (isset($step->dsl['descriptions'])) {
                foreach ($step->dsl['descriptions'] as $languageCode => $description) {
                    $objectStateUpdateStruct->descriptions[$languageCode] = $description;
                }
            }
            $state = $objectStateService->updateObjectState($state, $objectStateUpdateStruct);

            $this->setReferences($state, $step);
        }

        return $stateCollection;
    }

    /**
     * Handles the deletion step of object state migrations.
     */
    protected function delete($step)
    {
        $stateCollection = $this->matchObjectStates('delete', $step);

        $this->setReferences($stateCollection, $step);

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
    protected function matchObjectStates($action, $step)
    {
        if (!isset($step->dsl['match'])) {
            throw new \Exception("A match condition is required to $action an object state");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        return $this->objectStateMatcher->match($match);
    }

    /**
     * @param ObjectState $objectState
     * @param array $references the definitions of the references to set
     * @throws \InvalidArgumentException When trying to assign a reference to an unsupported attribute
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($objectState, array $references, $step)
    {
        $refs = array();

        foreach ($references as $reference) {
            switch ($reference['attribute']) {
                case 'object_state_id':
                case 'id':
                    $value = $objectState->id;
                    break;
                case 'priority':
                    $value = $objectState->priority;
                    break;
                default:
                    throw new \InvalidArgumentException('Object State Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }

    /**
     * @param array $matchCondition
     * @param string $mode
     * @param array $context
     * @throws \Exception
     * @return array
     */
    public function generateMigration(array $matchCondition, $mode, array $context = array())
    {
        $previousUserId = $this->loginUser($this->getAdminUserIdentifierFromContext($context));
        $objectStateCollection = $this->objectStateMatcher->match($matchCondition);
        $data = array();

        /** @var \eZ\Publish\API\Repository\Values\ObjectState\ObjectState $objectState */
        foreach ($objectStateCollection as $objectState) {

            $groupData = array(
                'type' => reset($this->supportedStepTypes),
                'mode' => $mode,
            );

            switch ($mode) {
                case 'create':
                    $groupData = array_merge(
                        $groupData,
                        array(
                            'object_state_group' => $objectState->getObjectStateGroup()->identifier,
                            'identifier' => $objectState->identifier,
                        )
                    );
                    break;
                case 'update':
                    $groupData = array_merge(
                        $groupData,
                        array(
                            'match' => array(
                                ObjectStateMatcher::MATCH_OBJECTSTATE_IDENTIFIER =>
                                    $objectState->getObjectStateGroup()->identifier . '/' . $objectState->identifier
                            ),
                            'identifier' => $objectState->identifier,
                        )
                    );
                    break;
                case 'delete':
                    $groupData = array_merge(
                        $groupData,
                        array(
                            'match' => array(
                                ObjectStateMatcher::MATCH_OBJECTSTATE_IDENTIFIER =>
                                    $objectState->getObjectStateGroup()->identifier . '/' . $objectState->identifier
                            )
                        )
                    );
                    break;
                default:
                    throw new \Exception("Executor 'object_state_group' doesn't support mode '$mode'");
            }

            if ($mode != 'delete') {
                $names = array();
                $descriptions = array();
                foreach($objectState->languageCodes as $languageCode) {
                    $names[$languageCode] =  $objectState->getName($languageCode);
                }
                foreach($objectState->languageCodes as $languageCode) {
                    $descriptions[$languageCode] =  $objectState->getDescription($languageCode);
                }
                $groupData = array_merge(
                    $groupData,
                    array(
                        'names' => $names,
                        'descriptions' => $descriptions,
                    )
                );
            }

            $data[] = $groupData;
        }

        $this->loginUser($previousUserId);
        return $data;
    }

    /**
     * @return string[]
     */
    public function listAllowedConditions()
    {
        return $this->objectStateMatcher->listAllowedConditions();
    }
}

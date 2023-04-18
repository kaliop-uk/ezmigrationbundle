<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\ObjectState\ObjectState;
use Kaliop\eZMigrationBundle\API\Collection\ObjectStateCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\EnumerableMatcherInterface;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateGroupMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateMatcher;

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
                throw new InvalidStepDefinitionException("The '$key' key is missing in a object state creation definition");
            }
        }

        if (!count($step->dsl['names'])) {
            throw new InvalidStepDefinitionException('No object state names have been defined. Need to specify at least one to create the state.');
        }

        $objectStateService = $this->repository->getObjectStateService();

        $objectStateGroupId = $step->dsl['object_state_group'];
        $objectStateGroupId = $this->resolveReference($objectStateGroupId);
        $objectStateGroup = $this->objectStateGroupMatcher->matchOneByKey($objectStateGroupId);

        $objectStateIdentifier = $this->resolveReference($step->dsl['identifier']);
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

        $this->validateResultsCount($stateCollection, $step);

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

        $this->validateResultsCount($stateCollection, $step);

        if (count($stateCollection) > 1 && isset($step->dsl['identifier'])) {
            throw new MigrationBundleException("Can not execute Object State update because multiple states match, and an identifier is specified in the dsl.");
        }

        $objectStateService = $this->repository->getObjectStateService();

        foreach ($stateCollection as $state) {
            $objectStateUpdateStruct = $objectStateService->newObjectStateUpdateStruct();

            if (isset($step->dsl['identifier'])) {
                $objectStateUpdateStruct->identifier = $this->resolveReference($step->dsl['identifier']);
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

        $this->validateResultsCount($stateCollection, $step);

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
            throw new InvalidStepDefinitionException("A match condition is required to $action an object state");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        $tolerateMisses = isset($step->dsl['match_tolerate_misses']) ? $this->resolveReference($step->dsl['match_tolerate_misses']) : false;

        return $this->objectStateMatcher->match($match, $tolerateMisses);
    }

    /**
     * @param ObjectState $objectState
     * @param array $references the definitions of the references to set
     * @throws InvalidStepDefinitionException
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($objectState, array $references, $step)
    {
        $refs = array();

        foreach ($references as $key => $reference) {
            $reference = $this->parseReferenceDefinition($key, $reference);
            switch ($reference['attribute']) {
                case 'object_state_id':
                case 'id':
                    $value = $objectState->id;
                    break;
                case 'priority':
                    $value = $objectState->priority;
                    break;
                default:
                    throw new InvalidStepDefinitionException('Object State Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }

    /**
     * @param array $matchConditions
     * @param string $mode
     * @param array $context
     * @return array
     * @throws \Exception
     */
    public function generateMigration(array $matchConditions, $mode, array $context = array())
    {
        $data = array();
        $previousUserId = $this->loginUser($this->getAdminUserIdentifierFromContext($context));
        try {
            $objectStateCollection = $this->objectStateMatcher->match($matchConditions);

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
                        throw new InvalidStepDefinitionException("Executor 'object_state_group' doesn't support mode '$mode'");
                }

                if ($mode != 'delete') {
                    $names = array();
                    $descriptions = array();
                    foreach ($objectState->languageCodes as $languageCode) {
                        $names[$languageCode] = $objectState->getName($languageCode);
                    }
                    foreach ($objectState->languageCodes as $languageCode) {
                        $descriptions[$languageCode] = $objectState->getDescription($languageCode);
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
        } catch (\Exception $e) {
            $this->loginUser($previousUserId);
            throw $e;
        }

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

<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroup;
use Kaliop\eZMigrationBundle\API\Collection\ObjectStateGroupCollection;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateGroupMatcher;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\API\EnumerableMatcherInterface;

/**
 * Handles object-state-group migrations.
 */
class ObjectStateGroupManager extends RepositoryExecutor implements MigrationGeneratorInterface, EnumerableMatcherInterface
{
    /**
     * @var array
     */
    protected $supportedStepTypes = array('object_state_group');
    protected $supportedActions = array('create', 'load', 'update', 'delete');

    /**
     * @var ObjectStateGroupMatcher
     */
    protected $objectStateGroupMatcher;

    /**
     * @param ObjectStateGroupMatcher $objectStateGroupMatcher
     */
    public function __construct(ObjectStateGroupMatcher $objectStateGroupMatcher)
    {
        $this->objectStateGroupMatcher = $objectStateGroupMatcher;
    }

    /**
     * Handles the create step of object state group migrations
     */
    protected function create($step)
    {
        foreach (array('names', 'identifier') as $key) {
            if (!isset($step->dsl[$key])) {
                throw new \Exception("The '$key' key is missing in a object state group creation definition");
            }
        }

        $objectStateService = $this->repository->getObjectStateService();

        $objectStateGroupIdentifier = $this->referenceResolver->resolveReference($step->dsl['identifier']);
        $objectStateGroupCreateStruct = $objectStateService->newObjectStateGroupCreateStruct($objectStateGroupIdentifier);
        $objectStateGroupCreateStruct->defaultLanguageCode = $this->getLanguageCode($step); // was: self::DEFAULT_LANGUAGE_CODE;

        foreach ($step->dsl['names'] as $languageCode => $name) {
            $objectStateGroupCreateStruct->names[$languageCode] = $name;
        }
        if (isset($step->dsl['descriptions'])) {
            foreach ($step->dsl['descriptions'] as $languageCode => $description) {
                $objectStateGroupCreateStruct->descriptions[$languageCode] = $description;
            }
        }

        $objectStateGroup = $objectStateService->createObjectStateGroup($objectStateGroupCreateStruct);

        $this->setReferences($objectStateGroup, $step);

        return $objectStateGroup;
    }

    protected function load($step)
    {
        $groupsCollection = $this->matchObjectStateGroups('load', $step);

        $this->setReferences($groupsCollection, $step);

        return $groupsCollection;
    }

    /**
     * Handles the update step of object state group migrations
     *
     * @todo add support for defaultLanguageCode
     */
    protected function update($step)
    {
        $objectStateService = $this->repository->getObjectStateService();

        $groupsCollection = $this->matchObjectStateGroups('update', $step);

        if (count($groupsCollection) > 1 && isset($step->dsl['references'])) {
            throw new \Exception("Can not execute Object State Group update because multiple groups match, and a references section is specified in the dsl. References can be set when only 1 state group matches");
        }

        if (count($groupsCollection) > 1 && isset($step->dsl['identifier'])) {
            throw new \Exception("Can not execute Object State Group update because multiple groups match, and an identifier is specified in the dsl.");
        }

        foreach ($groupsCollection as $objectStateGroup) {
            $objectStateGroupUpdateStruct = $objectStateService->newObjectStateGroupUpdateStruct();

            if (isset($step->dsl['identifier'])) {
                $objectStateGroupUpdateStruct->identifier = $this->referenceResolver->resolveReference($step->dsl['identifier']);
            }
            if (isset($step->dsl['names'])) {
                foreach ($step->dsl['names'] as $languageCode => $name) {
                    $objectStateGroupUpdateStruct->names[$languageCode] = $name;
                }
            }
            if (isset($step->dsl['descriptions'])) {
                foreach ($step->dsl['descriptions'] as $languageCode => $description) {
                    $objectStateGroupUpdateStruct->descriptions[$languageCode] = $description;
                }
            }
            $objectStateGroup = $objectStateService->updateObjectStateGroup($objectStateGroup, $objectStateGroupUpdateStruct);

            $this->setReferences($objectStateGroup, $step);
        }

        return $groupsCollection;
    }

    /**
     * Handles the delete step of object state group migrations
     */
    protected function delete($step)
    {
        $groupsCollection = $this->matchObjectStateGroups('delete', $step);

        $this->setReferences($groupsCollection, $step);

        $objectStateService = $this->repository->getObjectStateService();

        foreach ($groupsCollection as $objectStateGroup) {
            $objectStateService->deleteObjectStateGroup($objectStateGroup);
        }

        return $groupsCollection;
    }

    /**
     * @param string $action
     * @return ObjectStateGroupCollection
     * @throws \Exception
     */
    protected function matchObjectStateGroups($action, $step)
    {
        if (!isset($step->dsl['match'])) {
            throw new \Exception("A match condition is required to $action an object state group");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        return $this->objectStateGroupMatcher->match($match);
    }

    /**
     * @param ObjectStateGroup $objectStateGroup
     * @param array $references the definitions of the references to set
     * @throws \InvalidArgumentException When trying to assign a reference to an unsupported attribute
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($objectStateGroup, array $references, $step)
    {
        $refs = array();

        foreach ($references as $reference) {
            switch ($reference['attribute']) {
                case 'object_state_group_id':
                case 'id':
                    $value = $objectStateGroup->id;
                    break;
                case 'object_state_group_identifier':
                case 'identifier':
                    $value = $objectStateGroup->id;
                    break;
                default:
                    throw new \InvalidArgumentException('Object State Group Manager does not support setting references for attribute ' . $reference['attribute']);
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
        $objectStateGroupCollection = $this->objectStateGroupMatcher->match($matchCondition);
        $data = array();

        /** @var \eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroup $objectStateGroup */
        foreach ($objectStateGroupCollection as $objectStateGroup) {

            $groupData = array(
                'type' => reset($this->supportedStepTypes),
                'mode' => $mode,
            );

            switch ($mode) {
                case 'create':
                    $groupData = array_merge(
                        $groupData,
                        array(
                            'identifier' => $objectStateGroup->identifier,
                        )
                    );
                    break;
                case 'update':
                    $groupData = array_merge(
                        $groupData,
                        array(
                            'match' => array(
                                ObjectStateGroupMatcher::MATCH_OBJECTSTATEGROUP_IDENTIFIER => $objectStateGroup->identifier
                            ),
                            'identifier' => $objectStateGroup->identifier,
                        )
                    );
                    break;
                case 'delete':
                    $groupData = array_merge(
                        $groupData,
                        array(
                            'match' => array(
                                ObjectStateGroupMatcher::MATCH_OBJECTSTATEGROUP_IDENTIFIER => $objectStateGroup->identifier
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
                foreach($objectStateGroup->languageCodes as $languageCode) {
                    $names[$languageCode] =  $objectStateGroup->getName($languageCode);
                }
                foreach($objectStateGroup->languageCodes as $languageCode) {
                    $descriptions[$languageCode] =  $objectStateGroup->getDescription($languageCode);
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
        return $this->objectStateGroupMatcher->listAllowedConditions();
    }
}

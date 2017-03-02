<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\ObjectStateGroupCollection;

use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateGroupMatcher;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;

/**
 * Handles object-state-group migrations.
 */
class ObjectStateGroupManager extends RepositoryExecutor implements MigrationGeneratorInterface
{
    /**
     * @var array
     */
    protected $supportedStepTypes = array('object_state_group');

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
     *
     * @todo add support for flexible defaultLanguageCode
     */
    protected function create()
    {
        foreach (array('names', 'identifier') as $key) {
            if (!isset($this->dsl[$key])) {
                throw new \Exception("The '$key' key is missing in a object state group creation definition");
            }
        }

        $objectStateService = $this->repository->getObjectStateService();

        $objectStateGroupCreateStruct = $objectStateService->newObjectStateGroupCreateStruct($this->dsl['identifier']);
        $objectStateGroupCreateStruct->defaultLanguageCode = self::DEFAULT_LANGUAGE_CODE;

        foreach ($this->dsl['names'] as $languageCode => $name) {
            $objectStateGroupCreateStruct->names[$languageCode] = $name;
        }
        if (isset($this->dsl['descriptions'])) {
            foreach ($this->dsl['descriptions'] as $languageCode => $description) {
                $objectStateGroupCreateStruct->descriptions[$languageCode] = $description;
            }
        }

        $objectStateGroup = $objectStateService->createObjectStateGroup($objectStateGroupCreateStruct);

        $this->setReferences($objectStateGroup);

        return $objectStateGroup;
    }

    /**
     * Handles the update step of object state group migrations
     *
     * @todo add support for defaultLanguageCode
     */
    protected function update()
    {
        $objectStateService = $this->repository->getObjectStateService();

        $groupsCollection = $this->matchObjectStateGroups('update');

        if (count($groupsCollection) > 1 && isset($this->dsl['references'])) {
            throw new \Exception("Can not execute Object State Group update because multiple groups match, and a references section is specified in the dsl. References can be set when only 1 state group matches");
        }

        if (count($groupsCollection) > 1 && isset($this->dsl['identifier'])) {
            throw new \Exception("Can not execute Object State Group update because multiple groups match, and an identifier is specified in the dsl.");
        }

        foreach ($groupsCollection as $objectStateGroup) {
            $objectStateGroupUpdateStruct = $objectStateService->newObjectStateGroupUpdateStruct();

            if (isset($this->dsl['identifier'])) {
                $objectStateGroupUpdateStruct->identifier = $this->dsl['identifier'];
            }
            if (isset($this->dsl['names'])) {
                foreach ($this->dsl['names'] as $languageCode => $name) {
                    $objectStateGroupUpdateStruct->names[$languageCode] = $name;
                }
            }
            if (isset($this->dsl['descriptions'])) {
                foreach ($this->dsl['descriptions'] as $languageCode => $description) {
                    $objectStateGroupUpdateStruct->descriptions[$languageCode] = $description;
                }
            }
            $objectStateGroup = $objectStateService->updateObjectStateGroup($objectStateGroup, $objectStateGroupUpdateStruct);

            $this->setReferences($objectStateGroup);
        }

        return $groupsCollection;
    }

    /**
     * Handles the delete step of object state group migrations
     */
    protected function delete()
    {
        $groupsCollection = $this->matchObjectStateGroups('delete');

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
    protected function matchObjectStateGroups($action)
    {
        if (!isset($this->dsl['match'])) {
            throw new \Exception("A match condition is required to $action an object state group");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($this->dsl['match']);

        return $this->objectStateGroupMatcher->match($match);
    }

    /**
     * {@inheritdoc}
     * @param \eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroup $objectStateGroup
     */
    protected function setReferences($objectStateGroup)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        foreach ($this->dsl['references'] as $reference) {
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

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }

    /**
     * @param array $matchCondition
     * @param string $mode
     * @throws \Exception
     * @return array
     */
    public function generateMigration(array $matchCondition, $mode)
    {
        $previousUserId = $this->loginUser(self::ADMIN_USER_ID);
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
}

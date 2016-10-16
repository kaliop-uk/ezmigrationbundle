<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\ObjectStateGroupCollection;
use Kaliop\eZMigrationBundle\Core\Matcher\ObjectStateGroupMatcher;

class ObjectStateGroupManager extends RepositoryExecutor
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
     * Handle the create step of object state group migrations
     *
     * @todo add support for flexible defaultLanguageCode
     */
    protected function create()
    {
        foreach(array('names', 'identifier') as $key) {
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
    }

    /**
     * Handle the update step of object state group migrations
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
            //$objectStateGroup = $objectStateService->loadObjectStateGroup($this->dsl['id']);

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
    }

    /**
     * Handle the delete step of object state group migrations
     */
    protected function delete()
    {
        $groupsCollection = $this->matchObjectStateGroups('delete');

        $objectStateService = $this->repository->getObjectStateService();

        foreach ($groupsCollection as $objectStateGroup) {
            $objectStateService->deleteObjectStateGroup($objectStateGroup);
        }
    }

    /**
     * @param string $action
     * @return ObjectStateGroupCollection
     * @throws \Exception
     */
    protected function matchObjectStateGroups($action)
    {
        if (!isset($this->dsl['match'])) {
            throw new \Exception("A match condition is required to $action an ObjectStateGroup.");
        }

        $match = $this->dsl['match'];

        // convert the references passed in the match
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

        return $this->objectStateGroupMatcher->match($match);
    }

    /**
     * {@inheritdoc}
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
}

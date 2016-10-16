<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

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
    private $objectStateGroupMatcher;

    /**
     * @param ObjectStateGroupMatcher $objectStateGroupMatcher
     */
    public function __construct(ObjectStateGroupMatcher $objectStateGroupMatcher)
    {
        $this->objectStateGroupMatcher = $objectStateGroupMatcher;
    }

    /**
     * Handle the create step of object state group migrations
     */
    protected function create()
    {
        $objectStateService = $this->repository->getObjectStateService();

        $objectStateGroupCreateStruct = $objectStateService->newObjectStateGroupCreateStruct($this->dsl['identifier']);
        $objectStateGroupCreateStruct->defaultLanguageCode = self::DEFAULT_LANGUAGE_CODE;

        foreach ($this->dsl['names'] as $name) {
            $objectStateGroupCreateStruct->names[$name['languageCode']] = $name['name'];
        }

        $objectStateGroup = $objectStateService->createObjectStateGroup($objectStateGroupCreateStruct);

        $this->setReferences($objectStateGroup);
    }

    /**
     * Handle the update step of object state group migrations
     */
    protected function update()
    {
        $objectStateService = $this->repository->getObjectStateService();
        $objectStateGroup = $objectStateService->loadObjectStateGroup($this->dsl['id']);

        $objectStateGroupUpdateStruct = $objectStateService->newObjectStateGroupUpdateStruct();

        if(array_key_exists('names', $this->dsl)) {
            foreach ($this->dsl['names'] as $name) {
                $objectStateGroupUpdateStruct->names[$name['languageCode']] = $name['name'];
            }
        }

        $objectStateGroup = $objectStateService->updateObjectStateGroup($objectStateGroup, $objectStateGroupUpdateStruct);

        $this->setReferences($objectStateGroup);
    }

    /**
     * Handle the delete step of object state group migrations
     */
    protected function delete()
    {
        $objectStateService = $this->repository->getObjectStateService();

        $objectStateGroup = $objectStateService->loadObjectStateGroup($this->dsl['id']);

        $objectStateService->deleteObjectStateGroup($objectStateGroup);
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
                default:
                    throw new \InvalidArgumentException('Object State Group Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }
}

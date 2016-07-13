<?php


namespace Kaliop\eZMigrationBundle\Core\API\Managers;


use eZ\Publish\API\Repository\ObjectStateService;

class ObjectStateManager extends AbstractManager
{

    /**
     * {@inheritdoc}
     */
    protected function setReferences($object)
    {
        // TODO: Implement setReferences() method.
    }

    /**
     * {@inheritdoc}
     */
    public function create()
    {
        $this->loginUser();

        $objectStateService = $this->repository->getObjectStateService();

        $objectStateGroupId = $this->dsl['group_id'];
        if($this->isReference($objectStateGroupId)) {
            $objectStateGroupId = $this->getReference($objectStateGroupId);
        }

        $objectStateGroup = $objectStateService->loadObjectStateGroup($objectStateGroupId);

        $objectStateCreateStruct = $objectStateService->newObjectStateCreateStruct($this->dsl['identifier']);
        $objectStateCreateStruct->defaultLanguageCode = self::DEFAULT_LANGUAGE_CODE;

        foreach ($this->dsl['names'] as $name) {
            $objectStateCreateStruct->names[$name['languageCode']] = $name['name'];
        }

        $objectState = $objectStateService->createObjectState($objectStateGroup, $objectStateCreateStruct);

        $this->setReferences($objectState);
    }

    /**
     * {@inheritdoc}
     */
    public function update()
    {
        $this->loginUser();
        
        $objectStateService = $this->repository->getObjectStateService();
        
        $objectState = $objectStateService->loadObjectState($this->dsl['id']);
        
        $objectStateUpdateStruct = $objectStateService->newObjectStateUpdateStruct();
        
        foreach ($this->dsl['names'] as $name) {
            $objectStateUpdateStruct->names[$name['languageCode']] = $name['name'];
        }

        $objectState = $objectStateService->updateObjectState($objectState, $objectStateUpdateStruct);

        $this->setReferences($objectState);
    }

    /**
     * {@inheritdoc}
     */
    public function delete()
    {
        $this->loginUser();

        $objectStateService = $this->repository->getObjectStateService();

        $objectState = $objectStateService->loadObjectState($this->dsl['id']);

        $objectStateService->deleteObjectState($objectState);
    }
}
<?php


namespace Kaliop\eZMigrationBundle\Core\API\Managers;


use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroup;
use Kaliop\eZMigrationBundle\Core\API\ReferenceHandler;

class ObjectStateGroupManager extends AbstractManager
{

    /**
     * {@inheritdoc}
     */
    protected function setReferences($object)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        $referenceHandler = ReferenceHandler::instance();

        foreach ($this->dsl['references'] as $reference) {

            switch ($reference['attribute']) {
                case 'object_id':
                case 'id':
                    $value = $object->id;
                    break;
                default:
                    throw new \InvalidArgumentException('Object State Group Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $referenceHandler->addReference($reference['identifier'], $value);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function create()
    {
        $this->loginUser();

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
     * {@inheritdoc}
     */
    public function update()
    {
        $this->loginUser();

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
     * {@inheritdoc}
     */
    public function delete()
    {
        $this->loginUser();

        $objectStateService = $this->repository->getObjectStateService();

        $objectStateGroup = $objectStateService->loadObjectStateGroup($this->dsl['id']);

        $objectStateService->deleteObjectStateGroup($objectStateGroup);
    }
}
<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;

class ContentTypeGroupManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('content_type_group');
    protected $supportedActions = array('create', 'delete');

    protected function create()
    {
        $service = $this->repository->getContentTypeService();

        if (!isset($this->dsl['identifier'])) {
            throw new \Exception("The 'identifier' key is required to create a new content type group.");
        }

        $createStruct = $service->newContentTypeGroupCreateStruct($this->dsl['identifier']);
        $group = $service->createContentTypeGroup($createStruct);

        $this->setReferences($group);

        return $group;
    }

    protected function update()
    {
        throw new \Exception('Content type group update is not implemented yet');
    }

    protected function delete()
    {
        if (!isset($this->dsl['id']) and !isset($this->dsl['identifier'])) {
            throw new \Exception("The 'id' or 'identifier' key is required to delete a content type group.");
        }

        $service = $this->repository->getContentTypeService();
        if (isset($this->dsl['id'])) {
            $group = $service->loadContentTypeGroup($this->dsl['id']);
        } else {
            $group = $service->loadContentTypeGroupByIdentifier($this->dsl['identifier']);
        }

        $service->deleteContentTypeGroup($group);

        return $group;
    }

    /**
     * @param ContentTypeGroup $object
     * @return bool
     */
    protected function setReferences($object)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        foreach ($this->dsl['references'] as $reference) {

            switch ($reference['attribute']) {
                case 'content_type_group_id':
                case 'id':
                    $value = $object->id;
                    break;
                default:
                    throw new \InvalidArgumentException('Content Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }
}

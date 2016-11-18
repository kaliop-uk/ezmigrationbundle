<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\ContentTypeGroupCollection;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentTypeGroupMatcher;

class ContentTypeGroupManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('content_type_group');
    protected $supportedActions = array('create', 'delete');

    /**
     * @var ContentTypeGroupMatcher
     */
    protected $contentTypeGroupMatcher;

    /**
     * @param ContentTypeGroupMatcher $contentTypeGroupMatcher
     */
    public function __construct(ContentTypeGroupMatcher $contentTypeGroupMatcher)
    {
        $this->contentTypeGroupMatcher = $contentTypeGroupMatcher;
    }

    /**
     * @return \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup
     * @throws \Exception
     * @todo add support for setting creator id and creation date
     */
    protected function create()
    {
        if (!isset($this->dsl['identifier'])) {
            throw new \Exception("The 'identifier' key is required to create a new content type group.");
        }

        $contentTypeService = $this->repository->getContentTypeService();

        $createStruct = $contentTypeService->newContentTypeGroupCreateStruct($this->dsl['identifier']);
        $group = $contentTypeService->createContentTypeGroup($createStruct);

        $this->setReferences($group);

        return $group;
    }

    protected function update()
    {
        throw new \Exception('Content type group update is not implemented yet');
    }

    protected function delete()
    {
        $groupsCollection = $this->matchContentTypeGroups('delete');

        $objectStateService = $this->repository->getContentTypeService();

        foreach ($groupsCollection as $contentTypeGroup) {
            $objectStateService->deleteContentTypeGroup($contentTypeGroup);
        }

        return $groupsCollection;
    }

    /**
     * @param string $action
     * @return ContentTypeGroupCollection
     * @throws \Exception
     */
    protected function matchContentTypeGroups($action)
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

        return $this->contentTypeGroupMatcher->match($match);
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
                case 'content_type_group_identifier':
                case 'identifier':
                    $value = $object->identifier;
                    break;
                default:
                    throw new \InvalidArgumentException('Content Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }
}

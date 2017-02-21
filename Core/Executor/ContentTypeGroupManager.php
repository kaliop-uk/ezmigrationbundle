<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;
use Kaliop\eZMigrationBundle\API\Collection\ContentTypeGroupCollection;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentTypeGroupMatcher;

class ContentTypeGroupManager extends RepositoryExecutor implements MigrationGeneratorInterface
{
    protected $supportedStepTypes = array('content_type_group');

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
     * @todo add support for setting creator id
     */
    protected function create()
    {
        if (!isset($this->dsl['identifier'])) {
            throw new \Exception("The 'identifier' key is required to create a new content type group.");
        }

        $contentTypeService = $this->repository->getContentTypeService();

        $createStruct = $contentTypeService->newContentTypeGroupCreateStruct($this->dsl['identifier']);

        if (isset($this->dsl['creation_date'])) {
            $createStruct->creationDate = $this->toDateTime($this->dsl['creation_date']);
        }

        $group = $contentTypeService->createContentTypeGroup($createStruct);

        $this->setReferences($group);

        return $group;
    }

    protected function update()
    {
        $groupsCollection = $this->matchContentTypeGroups('update');

        if (count($groupsCollection) > 1 && array_key_exists('references', $this->dsl)) {
            throw new \Exception("Can not execute Content Type Group update because multiple types match, and a references section is specified in the dsl. References can be set when only 1 matches");
        }

        $contentTypeService = $this->repository->getContentTypeService();

        foreach ($groupsCollection as $key => $contentTypeGroup) {
            $updateStruct = $contentTypeService->newContentTypeGroupUpdateStruct();

            if (isset($this->dsl['identifier'])) {
                $updateStruct->identifier = $this->dsl['identifier'];
            }
            if (isset($this->dsl['modification_date'])) {
                $updateStruct->modificationDate = $this->toDateTime($this->dsl['modification_date']);
            }

            $contentTypeService->updateContentTypeGroup($contentTypeGroup, $updateStruct);
            // reload the group
            $group = $contentTypeService->loadContentTypeGroup($contentTypeGroup->id);
            $groupsCollection[$key] = $group;
        }

        $this->setReferences($groupsCollection);

        return $groupsCollection;
    }

    protected function delete()
    {
        $groupsCollection = $this->matchContentTypeGroups('delete');

        $contentTypeService = $this->repository->getContentTypeService();

        foreach ($groupsCollection as $contentTypeGroup) {
            $contentTypeService->deleteContentTypeGroup($contentTypeGroup);
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
     * @param ContentTypeGroup|ContentTypeGroupCollection $object
     * @return bool
     */
    protected function setReferences($object)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        if ($object instanceof ContentTypeGroupCollection) {
            if (count($object) > 1) {
                throw new \InvalidArgumentException('Content Type Group Manager does not support setting references for creating/updating of multiple Content Type Groups');
            }
            $object = reset($object);
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
                    throw new \InvalidArgumentException('Content Type Group Manager does not support setting references for attribute ' . $reference['attribute']);
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
        $contentTypeGroupCollection = $this->contentTypeGroupMatcher->match($matchCondition);
        $data = array();

        /** @var \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup $contentTypeGroup */
        foreach ($contentTypeGroupCollection as $contentTypeGroup) {

            $contentTypeGroupData = array(
                'type' => reset($this->supportedStepTypes),
                'mode' => $mode,
            );

            switch ($mode) {
                case 'create':
                    $contentTypeGroupData = array_merge(
                        $contentTypeGroupData,
                        array(
                            'identifier' => $contentTypeGroup->identifier,
                            'creation_date' => $contentTypeGroup->creationDate->getTimestamp()
                        )
                    );
                    break;
                case 'update':
                    $contentTypeGroupData = array_merge(
                        $contentTypeGroupData,
                        array(
                            'match' => array(
                                ContentTypeGroupMatcher::MATCH_CONTENTTYPEGROUP_IDENTIFIER => $contentTypeGroup->identifier
                            ),
                            'identifier' => $contentTypeGroup->identifier,
                            'modification_date' => $contentTypeGroup->modificationDate->getTimestamp()
                        )
                    );
                    break;
                case 'delete':
                    $contentTypeGroupData = array_merge(
                        $contentTypeGroupData,
                            array(
                                'match' => array(
                                    ContentTypeGroupMatcher::MATCH_CONTENTTYPEGROUP_IDENTIFIER => $contentTypeGroup->identifier
                                )
                            )
                        );
                    break;
                default:
                    throw new \Exception("Executor 'content_type_group' doesn't support mode '$mode'");
            }

            $data[] = $contentTypeGroupData;
        }

        $this->loginUser($previousUserId);
        return $data;
    }

    /**
     * @param int|string $date if integer, we assume a timestamp
     * @return \DateTime
     */
    protected function toDateTime($date)
    {
        if (is_int($date)) {
            return new \DateTime("@" . $date);
        } else {
            return new \DateTime($date);
        }
    }
}

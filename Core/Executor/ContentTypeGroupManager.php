<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;
use Kaliop\eZMigrationBundle\API\Collection\ContentTypeGroupCollection;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\API\EnumerableMatcherInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentTypeGroupMatcher;

/**
 * Handles content type groups migrations.
 */
class ContentTypeGroupManager extends RepositoryExecutor implements MigrationGeneratorInterface, EnumerableMatcherInterface
{
    protected $supportedStepTypes = array('content_type_group');

    /** @var ContentTypeGroupMatcher $contentTypeGroupMatcher */
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
    protected function create($step)
    {
        if (!isset($step->dsl['identifier'])) {
            throw new \Exception("The 'identifier' key is required to create a new content type group.");
        }

        $contentTypeService = $this->repository->getContentTypeService();

        $contentTypeGroupIdentifier = $this->referenceResolver->resolveReference($step->dsl['identifier']);
        $createStruct = $contentTypeService->newContentTypeGroupCreateStruct($contentTypeGroupIdentifier);

        if (isset($step->dsl['creation_date'])) {
            $createStruct->creationDate = $this->toDateTime($step->dsl['creation_date']);
        }

        $group = $contentTypeService->createContentTypeGroup($createStruct);

        $this->setReferences($group, $step);

        return $group;
    }

    protected function update($step)
    {
        $groupsCollection = $this->matchContentTypeGroups('update', $step);

        if (count($groupsCollection) > 1 && array_key_exists('references', $step->dsl)) {
            throw new \Exception("Can not execute Content Type Group update because multiple types match, and a references section is specified in the dsl. References can be set when only 1 matches");
        }

        $contentTypeService = $this->repository->getContentTypeService();

        foreach ($groupsCollection as $key => $contentTypeGroup) {
            $updateStruct = $contentTypeService->newContentTypeGroupUpdateStruct();

            if (isset($step->dsl['identifier'])) {
                $updateStruct->identifier = $this->referenceResolver->resolveReference($step->dsl['identifier']);
            }
            if (isset($step->dsl['modification_date'])) {
                $updateStruct->modificationDate = $this->toDateTime($step->dsl['modification_date']);
            }

            $contentTypeService->updateContentTypeGroup($contentTypeGroup, $updateStruct);
            // reload the group
            $group = $contentTypeService->loadContentTypeGroup($contentTypeGroup->id);
            $groupsCollection[$key] = $group;
        }

        $this->setReferences($groupsCollection, $step);

        return $groupsCollection;
    }

    protected function delete($step)
    {
        $groupsCollection = $this->matchContentTypeGroups('delete', $step);

        $this->setReferences($groupsCollection, $step);

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
    protected function matchContentTypeGroups($action, $step)
    {
        if (!isset($step->dsl['match'])) {
            throw new \Exception("A match condition is required to $action an object state group");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        return $this->contentTypeGroupMatcher->match($match);
    }

    /**
     * @param ContentTypeGroup $object
     * @param array $references the definitions of the references to set
     * @throws \InvalidArgumentException When trying to assign a reference to an unsupported attribute
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($object, array $references, $step)
    {
        $refs = array();

        foreach ($references as $reference) {

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
     * @return string[]
     */
    public function listAllowedConditions()
    {
        return $this->contentTypeGroupMatcher->listAllowedConditions();
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

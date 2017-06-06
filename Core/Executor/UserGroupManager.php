<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\Content\Content;
use Kaliop\eZMigrationBundle\Core\Matcher\UserGroupMatcher;
use Kaliop\eZMigrationBundle\API\Collection\UserGroupCollection;
use Kaliop\eZMigrationBundle\Core\Matcher\RoleMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\SectionMatcher;

/**
 * Handles user-group migrations.
 */
class UserGroupManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('user_group');

    protected $userGroupMatcher;
    protected $roleMatcher;
    protected $sectionMatcher;

    public function __construct(UserGroupMatcher $userGroupMatcher, RoleMatcher $roleMatcher, SectionMatcher $sectionMatcher)
    {
        $this->userGroupMatcher = $userGroupMatcher;
        $this->roleMatcher = $roleMatcher;
        $this->sectionMatcher = $sectionMatcher;
    }

    /**
     * Method to handle the create operation of the migration instructions
     */
    protected function create($step)
    {
        $userService = $this->repository->getUserService();

        $parentGroupId = $step->dsl['parent_group_id'];
        $parentGroupId = $this->referenceResolver->resolveReference($parentGroupId);
        $parentGroup = $this->userGroupMatcher->matchOneByKey($parentGroupId);

        $contentType = $this->repository->getContentTypeService()->loadContentTypeByIdentifier("user_group");

        $userGroupCreateStruct = $userService->newUserGroupCreateStruct($this->getLanguageCode($step), $contentType);
        $userGroupCreateStruct->setField('name', $step->dsl['name']);

        if (isset($step->dsl['remote_id'])) {
            $userGroupCreateStruct->remoteId = $step->dsl['remote_id'];
        }

        if (isset($step->dsl['description'])) {
            $userGroupCreateStruct->setField('description', $step->dsl['description']);
        }

        if (isset($step->dsl['section'])) {
            $sectionKey = $this->referenceResolver->resolveReference($step->dsl['section']);
            $section = $this->sectionMatcher->matchOneByKey($sectionKey);
            $userGroupCreateStruct->sectionId = $section->id;
        }

        $userGroup = $userService->createUserGroup($userGroupCreateStruct, $parentGroup);

        if (isset($step->dsl['roles'])) {
            $roleService = $this->repository->getRoleService();
            // we support both Ids and Identifiers
            foreach ($step->dsl['roles'] as $roleId) {
                $roleId = $this->referenceResolver->resolveReference($roleId);
                $role = $this->roleMatcher->matchOneByKey($roleId);
                $roleService->assignRoleToUserGroup($role, $userGroup);
            }
        }

        $this->setReferences($userGroup, $step);

        return $userGroup;
    }

    /**
     * Method to handle the update operation of the migration instructions
     *
     * @throws \Exception When the ID of the user group is missing from the migration definition.
     */
    protected function update($step)
    {
        $userGroupCollection = $this->matchUserGroups('update', $step);

        if (count($userGroupCollection) > 1 && isset($step->dsl['references'])) {
            throw new \Exception("Can not execute Group update because multiple groups match, and a references section is specified in the dsl. References can be set when only 1 group matches");
        }

        $userService = $this->repository->getUserService();
        $contentService = $this->repository->getContentService();

        foreach ($userGroupCollection as $key => $userGroup) {

            /** @var $updateStruct \eZ\Publish\API\Repository\Values\User\UserGroupUpdateStruct */
            $updateStruct = $userService->newUserGroupUpdateStruct();

            /** @var $contentUpdateStruct \eZ\Publish\API\Repository\Values\Content\ContentUpdateStruct */
            $contentUpdateStruct = $contentService->newContentUpdateStruct();

            if (isset($step->dsl['name'])) {
                $contentUpdateStruct->setField('name', $step->dsl['name']);
            }

            if (isset($step->dsl['remote_id'])) {
                $contentUpdateStruct->remoteId = $step->dsl['remote_id'];
            }

            if (isset($step->dsl['description'])) {
                $contentUpdateStruct->setField('description', $step->dsl['description']);
            }

            $updateStruct->contentUpdateStruct = $contentUpdateStruct;

            $userGroup = $userService->updateUserGroup($userGroup, $updateStruct);

            if (isset($step->dsl['parent_group_id'])) {
                $parentGroupId = $step->dsl['parent_group_id'];
                $parentGroupId = $this->referenceResolver->resolveReference($parentGroupId);
                $newParentGroup = $this->userGroupMatcher->matchOneByKey($parentGroupId);

                // Move group to new parent
                $userService->moveUserGroup($userGroup, $newParentGroup);
            }

            if (isset($step->dsl['section'])) {
                $this->setSection($userGroup, $step->dsl['section']);
            }

            $userGroupCollection[$key] = $userGroup;
        }

        $this->setReferences($userGroupCollection, $step);

        return $userGroupCollection;
    }

    /**
     * Method to handle the delete operation of the migration instructions
     *
     * @throws \Exception When there are no groups specified for deletion.
     */
    protected function delete($step)
    {
        $userGroupCollection = $this->matchUserGroups('delete', $step);

        $this->setReferences($userGroupCollection, $step);

        $userService = $this->repository->getUserService();

        foreach ($userGroupCollection as $userGroup) {
            $userService->deleteUserGroup($userGroup);
        }

        return $userGroupCollection;
    }

    /**
     * @param string $action
     * @return UserGroupCollection
     * @throws \Exception
     */
    protected function matchUserGroups($action, $step)
    {
        if (!isset($step->dsl['id']) && !isset($step->dsl['group']) && !isset($step->dsl['match'])) {
            throw new \Exception("The id of a user group or a match condition is required to $action it");
        }

        // Backwards compat
        if (isset($step->dsl['match'])) {
            $match = $step->dsl['match'];
        } else {
            if (isset($step->dsl['id'])) {
                $match = array('id' => $step->dsl['id']);
            }
            if (isset($step->dsl['group'])) {
                $match = array('id' => $step->dsl['group']);
            }
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($match);

        return $this->userGroupMatcher->match($match);
    }

    /**
     * Set references defined in the DSL for use in another step during the migrations.
     *
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute
     * @param \eZ\Publish\API\Repository\Values\User\UserGroup|UserGroupCollection $userGroup
     * @return boolean
     */
    protected function setReferences($userGroup, $step)
    {
        if (!array_key_exists('references', $step->dsl)) {
            return false;
        }

        $this->setReferencesCommon($userGroup, $step);
        $userGroup = $this->insureSingleEntity($userGroup, $step);

        foreach ($step->dsl['references'] as $reference) {

            switch ($reference['attribute']) {
                case 'object_id':
                case 'content_id':
                case 'user_group_id':
                case 'id':
                    $value = $userGroup->id;
                    break;
                default:
                    throw new \InvalidArgumentException('User Group Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            $this->referenceResolver->addReference($reference['identifier'], $value, $overwrite);
        }

        return true;
    }

    protected function setSection(Content $content, $sectionKey)
    {
        $sectionKey = $this->referenceResolver->resolveReference($sectionKey);
        $section = $this->sectionMatcher->matchOneByKey($sectionKey);

        $sectionService = $this->repository->getSectionService();
        $sectionService->assignSection($content->contentInfo, $section);
    }
}

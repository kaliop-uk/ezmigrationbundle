<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\User\UserGroup;
use Kaliop\eZMigrationBundle\API\Collection\UserGroupCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\Core\Matcher\RoleMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\SectionMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\UserGroupMatcher;

/**
 * Handles user-group migrations.
 */
class UserGroupManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('user_group');
    protected $supportedActions = array('create', 'load', 'update', 'delete');

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
        $parentGroupId = $this->resolveReference($parentGroupId);
        $parentGroup = $this->userGroupMatcher->matchOneByKey($parentGroupId);

        $contentType = $this->repository->getContentTypeService()->loadContentTypeByIdentifier($this->getUserGroupContentType($step));

        $userGroupCreateStruct = $userService->newUserGroupCreateStruct($this->getLanguageCode($step), $contentType);
        $userGroupCreateStruct->setField('name', $step->dsl['name']);

        if (isset($step->dsl['remote_id'])) {
            $userGroupCreateStruct->remoteId = $step->dsl['remote_id'];
        }

        if (isset($step->dsl['description'])) {
            $userGroupCreateStruct->setField('description', $step->dsl['description']);
        }

        if (isset($step->dsl['section'])) {
            $sectionKey = $this->resolveReference($step->dsl['section']);
            $section = $this->sectionMatcher->matchOneByKey($sectionKey);
            $userGroupCreateStruct->sectionId = $section->id;
        }

        $userGroup = $userService->createUserGroup($userGroupCreateStruct, $parentGroup);

        if (isset($step->dsl['roles'])) {
            $roleService = $this->repository->getRoleService();
            // we support both Ids and Identifiers
            foreach ($step->dsl['roles'] as $roleId) {
                $roleId = $this->resolveReference($roleId);
                $role = $this->roleMatcher->matchOneByKey($roleId);
                $roleService->assignRoleToUserGroup($role, $userGroup);
            }
        }

        $this->setReferences($userGroup, $step);

        return $userGroup;
    }

    protected function load($step)
    {
        $userGroupCollection = $this->matchUserGroups('load', $step);

        $this->validateResultsCount($userGroupCollection, $step);

        $this->setReferences($userGroupCollection, $step);

        return $userGroupCollection;
    }

    /**
     * Method to handle the update operation of the migration instructions
     *
     * @throws \Exception When the ID of the user group is missing from the migration definition.
     */
    protected function update($step)
    {
        $userGroupCollection = $this->matchUserGroups('update', $step);

        $this->validateResultsCount($userGroupCollection, $step);

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
                $parentGroupId = $this->resolveReference($parentGroupId);
                $newParentGroup = $this->userGroupMatcher->matchOneByKey($parentGroupId);

                // Move group to new parent
                $userService->moveUserGroup($userGroup, $newParentGroup);

                // reload user group to be able to set refs correctly
                $userGroup = $userService->loadUserGroup($userGroup->id);
            }

            if (isset($step->dsl['section'])) {
                $this->setSection($userGroup, $step->dsl['section']);

                /// @todo if we allow to set references to the group's section, here we should reload it
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

        $this->validateResultsCount($userGroupCollection, $step);

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
            throw new InvalidStepDefinitionException("The id of a user group or a match condition is required to $action it");
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

        $tolerateMisses = isset($step->dsl['match_tolerate_misses']) ? $this->resolveReference($step->dsl['match_tolerate_misses']) : false;

        return $this->userGroupMatcher->match($match, $tolerateMisses);
    }

    /**
     * @param UserGroup $userGroup
     * @param array $references the definitions of the references to set
     * @throws InvalidStepDefinitionException
     * @return array key: the reference names, values: the reference values
     *
     * @todo allow setting refs to all the attributes that can be gotten for Contents
     */
    protected function getReferencesValues($userGroup, array $references, $step)
    {
        $refs = array();

        foreach ($references as $key => $reference) {
            $reference = $this->parseReferenceDefinition($key, $reference);
            switch ($reference['attribute']) {
                case 'object_id':
                case 'content_id':
                case 'user_group_id':
                case 'id':
                    $value = $userGroup->id;
                    break;
                case 'parent_id':
                case 'parent_user_group_id':
                    $value = $userGroup->parentId;
                    break;
                case 'remote_id':
                    $value = $userGroup->contentInfo->remoteId;
                    break;
                case 'users_ids':
                    $value = [];
                    $userService = $this->repository->getUserService();
                    $limit = 100;
                    $offset = 0;
                    do {
                        $users = $userService->loadUsersOfUserGroup($userGroup, $offset, $limit);
                        foreach ($users as $user) {
                            $value[] = $user->id;
                        }
                        $offset += $limit;
                    } while (count($users));
                    break;
                default:
                    throw new InvalidStepDefinitionException('User Group Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }

    protected function setSection(Content $content, $sectionKey)
    {
        $sectionKey = $this->resolveReference($sectionKey);
        $section = $this->sectionMatcher->matchOneByKey($sectionKey);

        $sectionService = $this->repository->getSectionService();
        $sectionService->assignSection($content->contentInfo, $section);
    }
}

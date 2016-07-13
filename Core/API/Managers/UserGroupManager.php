<?php

namespace Kaliop\eZMigrationBundle\Core\API\Managers;

use eZ\Publish\API\Repository\Values\Content\Query\Criterion\ContentTypeIdentifier;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Field;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\LogicalAnd;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use Kaliop\eZMigrationBundle\Core\API\ReferenceHandler;
use eZ\Publish\API\Repository\Values\User\UserGroup;

/**
 * Class UserGroupManager
 *
 * Handles user group migrations.
 *
 * @package Kaliop\eZMigrationBundle\Core\API\Managers
 */
class UserGroupManager extends AbstractManager
{

    /**
     * Method to handle the create operation of the migration instructions
     */
    public function create()
    {
        // Authenticate the user
        $this->loginUser();

        $userService = $this->repository->getUserService();

        $parentGroupId = $this->dsl['parent_group_id'];
        if ($this->isReference($parentGroupId)) {
            $parentGroupId = $this->getReference($parentGroupId);
        }

        $parentGroup = $userService->loadUserGroup($parentGroupId);

        $contentType = $this->repository->getContentTypeService()->loadContentTypeByIdentifier("user_group");

        $userGroupCreateStruct = $userService->newUserGroupCreateStruct(self::DEFAULT_LANGUAGE_CODE, $contentType);
        $userGroupCreateStruct->setField('name', $this->dsl['name']);

        if (array_key_exists('description', $this->dsl)) {
            $userGroupCreateStruct->setField('description', $this->dsl['description']);
        }

        $userGroup = $userService->createUserGroup($userGroupCreateStruct, $parentGroup);

        if (array_key_exists('roles', $this->dsl)) {
            $roleService = $this->repository->getRoleService();
            foreach ($this->dsl['roles'] as $roleId) {
                if (is_int($roleId)) {
                    $role = $roleService->loadRole($roleId);
                } else {
                    // Assume it is an identifier if it is not an int
                    $role = $roleService->loadRoleByIdentifier($roleId);
                }
                $roleService->assignRoleToUserGroup($role, $userGroup);
            }
        }

        /*
         * Set any references based on the DSL
         */
        $this->setReferences($userGroup);
    }

    /**
     * Method to handle the update operation of the migration instructions
     *
     * @throws \InvalidArgumentException When the ID of the user group is missing from the migration definition.
     */
    public function update()
    {
        $this->loginUser();

        $userService = $this->repository->getUserService();
        $contentService = $this->repository->getContentService();

        $userGroup = null;
        
        if (array_key_exists('id', $this->dsl)) {
            $userGroup = $userService->loadUserGroup($this->dsl['id']);
        }

        if (array_key_exists('name', $this->dsl)) {
            $userGroupContent = $this->repository->getSearchService()->findSingle(
                new LogicalAnd(
                    [
                        new ContentTypeIdentifier('user_group'),
                        new Field('name', Operator::EQ, $this->dsl['name']),
                    ]
                )
            );

            $userGroup = $userService->loadUserGroup($userGroupContent->id);
        }
        
        if (null === $userGroup) {
            throw new \InvalidArgumentException('No user group has been specified for update. Please add the id or the name of the user group to the migration definition.');
        }

        /** @var $updateStruct \eZ\Publish\API\Repository\Values\User\UserGroupUpdateStruct */
        $updateStruct = $userService->newUserGroupUpdateStruct();

        /** @var $contentUpdateStruct \eZ\Publish\API\Repository\Values\Content\ContentUpdateStruct */
        $contentUpdateStruct = $contentService->newContentUpdateStruct();

        if (isset($this->dsl['name'])) {
            $contentUpdateStruct->setField('name', $this->dsl['name']);
        }

        if (isset($this->dsl['description'])) {
            $contentUpdateStruct->setField('description', $this->dsl['description']);
        }

        $updateStruct->contentUpdateStruct = $contentUpdateStruct;

        $userService->updateUserGroup($userGroup, $updateStruct);

        if(array_key_exists('parent_group_id', $this->dsl)) {
            $parentGroupId = $this->dsl['parent_group_id'];
            if($this->isReference($parentGroupId)) {
                $parentGroupId = $this->getReference($parentGroupId);
            }

            $newParentGroup = $userService->loadUserGroup($parentGroupId);

            // Move group to new parent
            $userService->moveUserGroup($userGroup, $newParentGroup);
        }

        if (array_key_exists('roles', $this->dsl)) {
            $roleService = $this->repository->getRoleService();

            $roleAssignments = $roleService->getRoleAssignmentsForUserGroup($userGroup);
            foreach ($roleAssignments as $roleAssignment) {
                $roleService->removeRoleAssignment($roleAssignment);
            }

            foreach ($this->dsl['roles'] as $roleId) {
                if (is_int($roleId)) {
                    $role = $roleService->loadRole($roleId);
                } else {
                    // Assume it is an identifier if it is not an int
                    $role = $roleService->loadRoleByIdentifier($roleId);
                }
                $roleService->assignRoleToUserGroup($role, $userGroup);
            }
        }

        $this->setReferences($userGroup);
    }

    /**
     * Method to handle the delete operation of the migration instructions
     *
     * @throws \InvalidArgumentException When there are no groups specified for deletion.
     */
    public function delete()
    {
        if(!array_key_exists('group', $this->dsl)) {
            throw new \InvalidArgumentException('No groups were specified for deletion.');
        }

        $this->loginUser();

        $userService = $this->repository->getUserService();

        $groupIds = $this->dsl['group'];
        if(!is_array($groupIds)) {
            $groupIds = array( $groupIds);
        }

        foreach($groupIds as $groupId) {
            $userGroup = $userService->loadUserGroup($groupId);
            $userService->deleteUserGroup($userGroup);
        }
    }

    /**
     * Set references defined in the DSL for use in another step during the migrations.
     *
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute
     * @param UserGroup $userGroup
     * @return boolean
     */
    protected function setReferences($userGroup)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        $referenceHandler = ReferenceHandler::instance();

        foreach ($this->dsl['references'] as $reference) {

            switch ($reference['attribute']) {
                case 'object_id':
                case 'id':
                    $value = $userGroup->id;
                    break;
                default:
                    throw new \InvalidArgumentException('User Group Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $referenceHandler->addReference($reference['identifier'], $value);
        }

        return true;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

/**
 * Handles user migrations.
 */
class UserManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('user');

    /**
     * Method to handle the create operation of the migration instructions
     *
     * Creates a user based on the DSL instructions.
     */
    protected function create()
    {
        $userService = $this->repository->getUserService();
        $contentTypeService = $this->repository->getContentTypeService();

        $userGroups = array();
        foreach ($this->dsl['groups'] as $group) {

            $groupId = $group;
            if ($this->referenceResolver->isReference($groupId)) {
                $groupId = $this->referenceResolver->getReferenceValue($groupId);
            }

            $userGroup = $userService->loadUserGroup($groupId);

            if ($userGroup) {
                $userGroups[] = $userGroup;
            }
        }

        // FIXME: Hard coding content type to user for now
        $userContentType = $contentTypeService->loadContentTypeByIdentifier('user');

        $userCreateStruct = $userService->newUserCreateStruct(
            $this->dsl['username'],
            $this->dsl['email'],
            $this->dsl['password'],
            self::DEFAULT_LANGUAGE_CODE,
            $userContentType
        );
        $userCreateStruct->setField('first_name', $this->dsl['first_name']);
        $userCreateStruct->setField('last_name', $this->dsl['last_name']);

        // Create the user
        $user = $userService->createUser($userCreateStruct, $userGroups);

        $this->setReferences($user);
    }

    /**
     * Method to handle the update operation of the migration instructions
     */
    protected function update()
    {
        if (!isset($this->dsl['id'])) {
            throw new \Exception('No user set for update.');
        }

        $userService = $this->repository->getUserService();

        $user = $userService->loadUser($this->dsl['id']);

        $userUpdateStruct = $userService->newUserUpdateStruct();

        if (isset($this->dsl['email'])) {
            $userUpdateStruct->email = $this->dsl['email'];
        }
        if (isset($this->dsl['password'])) {
            $userUpdateStruct->password = (string)$this->dsl['password'];
        }
        if (isset($this->dsl['enabled'])) {
            $userUpdateStruct->enabled = $this->dsl['enabled'];
        }

        $userService->updateUser($user, $userUpdateStruct);

        if (isset($this->dsl['groups'])) {
            $assignedGroups = $userService->loadUserGroupsOfUser($user);

            // Unassigning groups that are not in the list in the migration
            foreach ($assignedGroups as $assignedGroup) {
                if (!in_array($assignedGroup->id, $this->dsl['groups'])) {
                    $userService->unAssignUserFromUserGroup($user, $assignedGroup);
                }
            }

            // Assigning groups to the user
            foreach ($this->dsl['groups'] as $groupToAssignId) {
                foreach ($assignedGroups as $assignedGroup) {
                    // Make sure we assign the user only to groups he isn't already assigned to
                    if ($assignedGroup->id == $groupToAssignId) {
                        break;
                    } else {
                        $groupToAssign = $userService->loadUserGroup($groupToAssignId);
                        $userService->assignUserToUserGroup($user, $groupToAssign);
                    }
                }
            }
        }

        $this->setReferences($user);
    }

    /**
     * Method to handle the delete operation of the migration instructions
     */
    protected function delete()
    {
        if (!isset($this->dsl['user_id']) && !isset($this->dsl['email']) && !isset($this->dsl['username'])) {
            throw new \Exception('No user set for update.');
        }

        $userService = $this->repository->getUserService();

        $usersToDelete = array();

        if (isset($this->dsl['user_id'])) {
            if (!is_array($this->dsl['user_id'])) {
                $this->dsl['user_id'] = array($this->dsl['user_id']);
            }
            foreach ($this->dsl['user_id'] as $id) {
                array_push($usersToDelete,$userService->loadUser($id));
            }
        }
        if (isset($this->dsl['email'])) {
            if (!is_array($this->dsl['email'])) {
                $this->dsl['email'] = array($this->dsl['email']);
            }
            foreach ($this->dsl['email'] as $email) {
                $usersToDelete = array_merge($usersToDelete,$userService->loadUsersByEmail($email));
            }
        }
        if (isset($this->dsl['username'])) {
            if (!is_array($this->dsl['username'])) {
                $this->dsl['username'] = array($this->dsl['username']);
            }
            foreach ($this->dsl['username'] as $username) {
                array_push($usersToDelete,$userService->loadUserByLogin($username));
            }
        }

        foreach($usersToDelete as $user){
            $userService->deleteUser($user);
        }
    }

    /**
     * Sets references to object attributes.
     *
     * The User manager currently only supports setting references to user_id.
     *
     * @param \eZ\Publish\API\Repository\Values\User\User $user
     * @throws \InvalidArgumentException when trying to set references to unsupported attributes
     * @return boolean
     */
    protected function setReferences($user)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        foreach ($this->dsl['references'] as $reference) {
            switch ($reference['attribute']) {
                case 'user_id':
                case 'id':
                    $value = $user->id;
                    break;
                default:
                    throw new \InvalidArgumentException('User Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }
}

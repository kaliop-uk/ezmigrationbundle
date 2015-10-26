<?php

namespace Kaliop\Migration\Core\API\Managers;


use eZ\Publish\API\Repository\Values\User\Role;
use eZ\Publish\API\Repository\RoleService;
use eZ\Publish\API\Repository\UserService;
use Kaliop\Migration\Core\API\ReferenceHandler;

/**
 * Class RoleManager
 *
 * Handles the role migration definitions.
 *
 * @package Kaliop\Migration\Core\API\Managers
 */
class RoleManager extends AbstractManager
{

    /**
     * Method to handle the create operation of the migration instructions
     */
    public function create()
    {

        // Authenticate the user
        $this->loginUser();

        $roleService = $this->repository->getRoleService();
        $userService = $this->repository->getUserService();

        $roleCreateStruct = $roleService->newRoleCreateStruct($this->dsl['name']);

        // Publish new role
        $role = $roleService->createRole($roleCreateStruct);

        if (array_key_exists('policies', $this->dsl)) {
            $this->addPolicies($role, $roleService, $this->dsl['policies']);
        }

        if (array_key_exists('assign', $this->dsl)) {
            $this->assignRole($role, $roleService, $userService, $this->dsl['assign']);
        }

        $this->setReferences($role);
    }

    /**
     * Method to handle the update operation of the migration instructions
     */
    public function update()
    {
        // Authenticate the user
        $this->loginUser();

        $roleService = $this->repository->getRoleService();
        $userService = $this->repository->getUserService();

        if (array_key_exists('name', $this->dsl)) {
            $role = $roleService->loadRoleByIdentifier($this->dsl['name']);

            if (array_key_exists('assign', $this->dsl)) {
                $this->assignRole($role, $roleService, $userService, $this->dsl['assign']);
            }

            if (array_key_exists('unassign', $this->dsl)) {
                if (array_key_exists('users', $this->dsl['unassign'])) {
                    $this->unassignRoleFromUsers($role, $roleService, $userService, $this->dsl['unassign']['users']);
                }
                if (array_key_exists('groups', $this->dsl['unassign'])) {
                    $this->unassignRoleFromGroups($role, $roleService, $userService, $this->dsl['unassign']['groups']);
                }
            }

            if (array_key_exists('policies', $this->dsl)) {
                if (array_key_exists('add', $this->dsl['policies'])) {
                    $this->addPolicies($role, $roleService, $this->dsl['policies']['add']);
                }
                if (array_key_exists('remove', $this->dsl['policies'])) {
                    $this->removePolicies($role, $roleService, $this->dsl['policies']['remove']);
                }
            }
        }

        $this->setReferences($role);
    }

    /**
     * Method to handle the delete operation of the migration instructions
     */
    public function delete()
    {
        //Get the eZ 5 API Repository and the required services

        // Authenticate the user
        $this->loginUser();

        $roleService = $this->repository->getRoleService();

        if (array_key_exists('name', $this->dsl)) {
            $role = $roleService->loadRoleByIdentifier($this->dsl['name']);
            $roleService->deleteRole($role);
        }
    }

    /**
     * Set references to object attributes to be retrieved later.
     *
     * The Role Manager currently support setting references to role_ids.
     *
     * @param \eZ\Publish\API\Repository\Values\User\Role $role
     * @throws \InvalidArgumentException When trying to assign a reference to an unsupported attribute
     * @return boolean
     */
    protected function setReferences($role)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        $referenceHandler = ReferenceHandler::instance();

        foreach ($this->dsl['references'] as $reference) {
            switch ($reference['attribute']) {
                case 'role_id':
                case 'id':
                    $value = $role->id;
                    break;
                case 'identifier':
                case 'role_identidier':
                    $value = $role->identifier;
                    break;
                default:
                    throw new \InvalidArgumentException('Role Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $referenceHandler->addReference($reference['identifier'], $value);
        }

        return true;
    }

    /**
     * Create a new Limitation object based on the type and value in the $limitation array.
     *
     * <pre>
     * $limitation = array(
     *  'type' => Type of the limitation
     *  'value' => array(Values to base the limitation on)
     * )
     * </pre>
     *
     * @param \eZ\Publish\API\Repository\RoleService $roleService
     * @param array $limitation
     * @return \eZ\Publish\API\Repository\Values\User\Limitation
     */
    private function createLimitation(RoleService $roleService, array $limitation)
    {
        $limitationType = $roleService->getLimitationType($limitation['type']);

        $limitationValue = $limitation['value'];
        if(!is_array($limitationValue)) {
            $limitationValue = array($limitationValue);
        }

        foreach( $limitationValue as $id => $value) {
            if ($this->isReference($value)) {
                $value = $this->getReference($value);
                $limitationValue[$id] = $value;
            }
        }

        return $limitationType->buildValue($limitationValue);
    }

    /**
     * Assign a role to users and groups in the assignment array.
     *
     * <pre>
     * $assignments = array(
     *      array(
     *          'type' => 'user',
     *          'ids' => array( user ids ),
     *          'limitation' => array( limitations )
     *      )
     * )
     * </pre>
     *
     * @param \eZ\Publish\API\Repository\Values\User\Role $role
     * @param \eZ\Publish\API\Repository\RoleService $roleService
     * @param \eZ\Publish\API\Repository\UserService $userService
     * @param array $assignments
     */
    private function assignRole(Role $role, RoleService $roleService, UserService $userService, array $assignments)
    {
        foreach ($assignments as $assign) {
            switch ($assign['type']) {
                case 'user':
                    foreach ($assign['ids'] as $userId) {
                        $user = $userService->loadUser($userId);

                        if (!array_key_exists('limitation', $assign)) {
                            $roleService->assignRoleToUser($role, $user);
                        } else {
                            foreach ($assign['limitation'] as $limitation) {
                                $limitationObject = $this->createLimitation($roleService, $limitation);
                                $roleService->assignRoleToUser($role, $user, $limitationObject);
                            }
                        }
                    }
                    break;
                case 'group':
                    foreach ($assign['ids'] as $groupId) {
                        $group = $userService->loadUserGroup($groupId);

                        if (!array_key_exists('limitation', $assign)) {
                            $roleService->assignRoleToUserGroup($role, $group);
                        } else {
                            foreach ($assign['limitation'] as $limitation) {
                                $limitationObject = $this->createLimitation($roleService, $limitation);
                                $roleService->assignRoleToUserGroup($role, $group, $limitationObject);
                            }
                        }
                    }
                    break;
            }
        }
    }

    /**
     * Unassign a role from a list of users based on their user ids.
     *
     * @param Role $role
     * @param RoleService $roleService
     * @param UserService $userService
     * @param array $userIds
     */
    private function unassignRoleFromUsers(
        Role $role,
        RoleService $roleService,
        UserService $userService,
        array $userIds
    ) {
        foreach ($userIds as $userId) {
            $user = $userService->loadUser($userId);
            $roleService->unassignRoleFromUser($role, $user);
        }
    }

    /**
     * Unassign a role from a list of user groups based on their id.
     *
     * @param Role $role
     * @param RoleService $roleService
     * @param UserService $userService
     * @param array $userGroupIds
     */
    private function unassignRoleFromGroups(
        Role $role,
        RoleService $roleService,
        UserService $userService,
        array $userGroupIds
    ) {
        foreach ($userGroupIds as $userGroupId) {
            $userGroup = $userService->loadUserGroup($userGroupId);
            $roleService->unassignRoleFromUserGroup($role, $userGroup);
        }
    }

    /**
     * Add new policies to the $role Role.
     *
     * @param \eZ\Publish\API\Repository\Values\User\Role $role
     * @param \eZ\Publish\API\Repository\RoleService $roleService
     * @param array $policies
     */
    private function addPolicies(Role $role, RoleService $roleService, array $policies)
    {
        foreach ($policies as $policy) {
            $policyCreateStruct = $roleService->newPolicyCreateStruct($policy['module'], $policy['function']);

            if (array_key_exists('limitation', $policy)) {
                foreach ($policy['limitation'] as $limitation) {
                    $limitationObject = $this->createLimitation($roleService, $limitation);
                    $policyCreateStruct->addLimitation($limitationObject);
                }
            }

            $roleService->addPolicy($role, $policyCreateStruct);
        }
    }

    /**
     * Remove a list of policies from a Role.
     *
     * @param \eZ\Publish\API\Repository\Values\User\Role $role
     * @param \eZ\Publish\API\Repository\RoleService $roleService
     * @param array $policies An array of policy ids to remove.
     */
    private function removePolicies(Role $role, RoleService $roleService, array $policies)
    {
        $rolePolicies = $role->getPolicies();
        foreach ($rolePolicies as $rolePolicy) {
            if (in_array($rolePolicy->id, $policies)) {
                $roleService->removePolicy($role, $rolePolicy);
            }
        }
    }
}

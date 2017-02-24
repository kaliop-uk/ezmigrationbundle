<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\User\Role;
use eZ\Publish\API\Repository\RoleService;
use eZ\Publish\API\Repository\UserService;
use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;
use Kaliop\eZMigrationBundle\API\Collection\RoleCollection;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\Core\Helper\LimitationConverter;
use Kaliop\eZMigrationBundle\Core\Matcher\RoleMatcher;

/**
 * Handles the role migration definitions.
 */
class RoleManager extends RepositoryExecutor implements MigrationGeneratorInterface
{
    protected $supportedStepTypes = array('role');

    protected $limitationConverter;
    protected $roleMatcher;

    public function __construct(RoleMatcher $roleMatcher, LimitationConverter $limitationConverter)
    {
        $this->roleMatcher = $roleMatcher;
        $this->limitationConverter = $limitationConverter;
    }

    /**
     * Method to handle the create operation of the migration instructions
     */
    protected function create()
    {
        $roleService = $this->repository->getRoleService();
        $userService = $this->repository->getUserService();

        $roleCreateStruct = $roleService->newRoleCreateStruct($this->dsl['name']);

        // Publish new role
        $role = $roleService->createRole($roleCreateStruct);
        if (is_callable(array($roleService, 'publishRoleDraft'))) {
            $roleService->publishRoleDraft($role);
        }

        if (isset($this->dsl['policies'])) {
            foreach ($this->dsl['policies'] as $key => $ymlPolicy) {
                $this->addPolicy($role, $roleService, $ymlPolicy);
            }
        }

        if (isset($this->dsl['assign'])) {
            $this->assignRole($role, $roleService, $userService, $this->dsl['assign']);
        }

        $this->setReferences($role);

        return $role;
    }

    /**
     * Method to handle the update operation of the migration instructions
     */
    protected function update()
    {
        $roleCollection = $this->matchRoles('update');

        if (count($roleCollection) > 1 && isset($this->dsl['references'])) {
            throw new \Exception("Can not execute Role update because multiple roles match, and a references section is specified in the dsl. References can be set when only 1 role matches");
        }

        if (count($roleCollection) > 1 && isset($this->dsl['new_name'])) {
            throw new \Exception("Can not execute Role update because multiple roles match, and a new_name is specified in the dsl.");
        }

        $roleService = $this->repository->getRoleService();
        $userService = $this->repository->getUserService();

        /** @var \eZ\Publish\API\Repository\Values\User\Role $role */
        foreach ($roleCollection as $key => $role) {

            // Updating role name
            if (isset($this->dsl['new_name'])) {
                $update = $roleService->newRoleUpdateStruct();
                $update->identifier = $this->dsl['new_name'];
                $role = $roleService->updateRole($role, $update);
            }

            if (isset($this->dsl['policies'])) {
                $ymlPolicies = $this->dsl['policies'];

                // Removing all policies so we can add them back.
                // TODO: Check and update policies instead of remove and add.
                $policies = $role->getPolicies();
                foreach ($policies as $policy) {
                    $roleService->deletePolicy($policy);
                }

                foreach ($ymlPolicies as $ymlPolicy) {
                    $this->addPolicy($role, $roleService, $ymlPolicy);
                }
            }

            if (isset($this->dsl['assign'])) {
                $this->assignRole($role, $roleService, $userService, $this->dsl['assign']);
            }

            $roleCollection[$key] = $role;
        }

        $this->setReferences($roleCollection);

        return $roleCollection;
    }

    /**
     * Method to handle the delete operation of the migration instructions
     */
    protected function delete()
    {
        $roleCollection = $this->matchRoles('delete');

        $roleService = $this->repository->getRoleService();

        foreach ($roleCollection as $role) {
            $roleService->deleteRole($role);
        }

        return $roleCollection;
    }

    /**
     * @param string $action
     * @return RoleCollection
     * @throws \Exception
     */
    protected function matchRoles($action)
    {
        if (!isset($this->dsl['name']) && !isset($this->dsl['match'])) {
            throw new \Exception("The name of a role or a match condition is required to $action it.");
        }

        // Backwards compat
        if (!isset($this->dsl['match'])) {
            $this->dsl['match'] = array('identifier' => $this->dsl['name']);
        }

        $match = $this->dsl['match'];

        // convert the references passed in the match
        foreach ($match as $condition => $values) {
            if (is_array($values)) {
                foreach ($values as $position => $value) {
                    $match[$condition][$position] = $this->referenceResolver->resolveReference($value);
                }
            } else {
                $match[$condition] = $this->referenceResolver->resolveReference($values);
            }
        }

        return $this->roleMatcher->match($match);
    }

    /**
     * Set references to object attributes to be retrieved later.
     *
     * The Role Manager currently support setting references to role_ids.
     *
     * @param \eZ\Publish\API\Repository\Values\User\Role|RoleCollection $role
     * @throws \InvalidArgumentException When trying to assign a reference to an unsupported attribute
     * @return boolean
     */
    protected function setReferences($role)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        if ($role instanceof RoleCollection) {
            if (count($role) > 1) {
                throw new \InvalidArgumentException('Role Manager does not support setting references for creating/updating of multiple roles');
            }
            $role = reset($role);
        }

        foreach ($this->dsl['references'] as $reference) {
            switch ($reference['attribute']) {
                case 'role_id':
                case 'id':
                    $value = $role->id;
                    break;
                case 'identifier':
                case 'role_identifier':
                    $value = $role->identifier;
                    break;
                default:
                    throw new \InvalidArgumentException('Role Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }

    /**
     * Create a new Limitation object based on the type and value in the $limitation array.
     *
     * <pre>
     * $limitation = array(
     *  'identifier' => Type of the limitation
     *  'values' => array(Values to base the limitation on)
     * )
     * </pre>
     *
     * @param \eZ\Publish\API\Repository\RoleService $roleService
     * @param array $limitation
     * @return \eZ\Publish\API\Repository\Values\User\Limitation
     */
    private function createLimitation(RoleService $roleService, array $limitation)
    {
        $limitationType = $roleService->getLimitationType($limitation['identifier']);

        $limitationValue = is_array($limitation['values']) ? $limitation['values'] : array($limitation['values']);

        foreach ($limitationValue as $id => $value) {
            $limitationValue[$id] = $this->referenceResolver->resolveReference($value);
        }
        $limitationValue = $this->limitationConverter->resolveLimitationValue($limitation['identifier'], $limitationValue);
        return $limitationType->buildValue($limitationValue);
    }

    /**
     * Assign a role to users and groups in the assignment array.
     *
     * <pre>
     * $assignments = array(
     *      array(
     *          'type' => 'user',
     *          'ids' => array(user ids),
     *          'limitation' => array(limitations)
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
                        $userId = $this->referenceResolver->resolveReference($userId);

                        $user = $userService->loadUser($userId);

                        if (!isset($assign['limitations'])) {
                            $roleService->assignRoleToUser($role, $user);
                        } else {
                            foreach ($assign['limitations'] as $limitation) {
                                $limitationObject = $this->createLimitation($roleService, $limitation);
                                $roleService->assignRoleToUser($role, $user, $limitationObject);
                            }
                        }
                    }
                    break;
                case 'group':
                    foreach ($assign['ids'] as $groupId) {
                        $groupId = $this->referenceResolver->resolveReference($groupId);

                        $group = $userService->loadUserGroup($groupId);

                        if (!isset($assign['limitations'])) {
                            // q: why are we swallowing exceptions here ?
                            //try {
                                $roleService->assignRoleToUserGroup($role, $group);
                            //} catch (InvalidArgumentException $e) {}
                        } else {
                            foreach ($assign['limitations'] as $limitation) {
                                $limitationObject = $this->createLimitation($roleService, $limitation);
                                // q: why are we swallowing exceptions here ?
                                //try {
                                    $roleService->assignRoleToUserGroup($role, $group, $limitationObject);
                                //} catch (InvalidArgumentException $e) {}
                            }
                        }
                    }
                    break;
            }
        }
    }

    /**
     * Add new policies to the $role Role.
     *
     * @param \eZ\Publish\API\Repository\Values\User\Role $role
     * @param \eZ\Publish\API\Repository\RoleService $roleService
     * @param array $policy
     */
    private function addPolicy(Role $role, RoleService $roleService, array $policy)
    {
        $policyCreateStruct = $roleService->newPolicyCreateStruct($policy['module'], $policy['function']);

        if (array_key_exists('limitations', $policy)) {
            foreach ($policy['limitations'] as $limitation) {
                $limitationObject = $this->createLimitation($roleService, $limitation);
                $policyCreateStruct->addLimitation($limitationObject);
            }
        }

        $roleService->addPolicy($role, $policyCreateStruct);
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
        $roleCollection = $this->roleMatcher->match($matchCondition);
        $data = array();

        /** @var \eZ\Publish\API\Repository\Values\User\Role $role */
        foreach ($roleCollection as $role) {
            $roleData = array(
                'type' => reset($this->supportedStepTypes),
                'mode' => $mode
            );

            switch ($mode) {
                case 'create':
                    $roleData = array_merge(
                        $roleData,
                        array(
                            'name' => $role->identifier
                        )
                    );
                    break;
                case 'update':
                case 'delete':
                    $roleData = array_merge(
                        $roleData,
                        array(
                            'match' => array(
                                RoleMatcher::MATCH_ROLE_IDENTIFIER => $role->identifier
                            )
                        )
                    );
                    break;
                default:
                    throw new \Exception("Executor 'role' doesn't support mode '$mode'");
            }

            if ($mode != 'delete') {
                $policies = array();
                foreach ($role->getPolicies() as $policy) {
                    $limitations = array();

                    foreach ($policy->getLimitations() as $limitation) {
                        $limitations[] = $this->limitationConverter->getLimitationArrayWithIdentifiers($limitation);
                    }

                    $policies[] = array(
                        'module' => $policy->module,
                        'function' => $policy->function,
                        'limitations' => $limitations
                    );
                }

                $roleData = array_merge(
                    $roleData,
                    array(
                        'policies' => $policies
                    )
                );
            }

            $data[] = $roleData;
        }

        $this->loginUser($previousUserId);
        return $data;
    }
}

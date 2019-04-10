<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\User\Role;
use eZ\Publish\API\Repository\RoleService;
use eZ\Publish\API\Repository\UserService;
use Kaliop\eZMigrationBundle\API\Collection\RoleCollection;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\API\EnumerableMatcherInterface;
use Kaliop\eZMigrationBundle\Core\Helper\LimitationConverter;
use Kaliop\eZMigrationBundle\Core\Matcher\RoleMatcher;
use eZ\Publish\API\Repository\Values\User\Limitation;

/**
 * Handles role migrations.
 */
class RoleManager extends RepositoryExecutor implements MigrationGeneratorInterface, EnumerableMatcherInterface
{
    protected $supportedStepTypes = array('role');
    protected $supportedActions = array('create', 'load', 'update', 'delete');

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
    protected function create($step)
    {
        $roleService = $this->repository->getRoleService();
        $userService = $this->repository->getUserService();

        $roleName = $this->referenceResolver->resolveReference($step->dsl['name']);
        $roleCreateStruct = $roleService->newRoleCreateStruct($roleName);

        // Publish new role
        $role = $roleService->createRole($roleCreateStruct);
        if (is_callable(array($roleService, 'publishRoleDraft'))) {
            $roleService->publishRoleDraft($role);
        }

        if (isset($step->dsl['policies'])) {
            foreach ($step->dsl['policies'] as $key => $ymlPolicy) {
                $this->addPolicy($role, $roleService, $ymlPolicy);
            }
        }

        if (isset($step->dsl['assign'])) {
            $this->assignRole($role, $roleService, $userService, $step->dsl['assign']);
        }

        $this->setReferences($role, $step);

        return $role;
    }

    protected function load($step)
    {
        $roleCollection = $this->matchRoles('load', $step);

        $this->setReferences($roleCollection, $step);

        return $roleCollection;
    }

    /**
     * Method to handle the update operation of the migration instructions
     */
    protected function update($step)
    {
        $roleCollection = $this->matchRoles('update', $step);

        if (count($roleCollection) > 1 && isset($step->dsl['references'])) {
            throw new \Exception("Can not execute Role update because multiple roles match, and a references section is specified in the dsl. References can be set when only 1 role matches");
        }

        if (count($roleCollection) > 1 && isset($step->dsl['new_name'])) {
            throw new \Exception("Can not execute Role update because multiple roles match, and a new_name is specified in the dsl.");
        }

        $roleService = $this->repository->getRoleService();
        $userService = $this->repository->getUserService();

        /** @var \eZ\Publish\API\Repository\Values\User\Role $role */
        foreach ($roleCollection as $key => $role) {

            // Updating role name
            if (isset($step->dsl['new_name'])) {
                $update = $roleService->newRoleUpdateStruct();
                $newRoleName = $this->referenceResolver->resolveReference($step->dsl['new_name']);
                $update->identifier = $this->referenceResolver->resolveReference($newRoleName);
                $role = $roleService->updateRole($role, $update);
            }

            if (isset($step->dsl['policies'])) {
                $ymlPolicies = $step->dsl['policies'];

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

            if (isset($step->dsl['assign'])) {
                $this->assignRole($role, $roleService, $userService, $step->dsl['assign']);
            }

            $roleCollection[$key] = $role;
        }

        $this->setReferences($roleCollection, $step);

        return $roleCollection;
    }

    /**
     * Method to handle the delete operation of the migration instructions
     */
    protected function delete($step)
    {
        $roleCollection = $this->matchRoles('delete', $step);

        $this->setReferences($roleCollection, $step);

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
    protected function matchRoles($action, $step)
    {
        if (!isset($step->dsl['name']) && !isset($step->dsl['match'])) {
            throw new \Exception("The name of a role or a match condition is required to $action it");
        }

        // Backwards compat
        if (isset($step->dsl['match'])) {
            $match = $step->dsl['match'];
        } else {
            $match = array('identifier' => $step->dsl['name']);
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($match);

        return $this->roleMatcher->match($match);
    }

    /**
     * @param Role $role
     * @param array $references the definitions of the references to set
     * @throws \InvalidArgumentException When trying to assign a reference to an unsupported attribute
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($role, array $references, $step)
    {
        $refs = array();

        foreach ($references as $reference) {
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
                        if (!($limitation instanceof Limitation)) {
                            throw new \Exception("The role contains an invalid limitation for policy {$policy->module}/{$policy->function}, we can not reliably generate its definition.");
                        }
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

    /**
     * @return string[]
     */
    public function listAllowedConditions()
    {
        return $this->roleMatcher->listAllowedConditions();
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
     * @return Limitation
     */
    protected function createLimitation(RoleService $roleService, array $limitation)
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
    protected function assignRole(Role $role, RoleService $roleService, UserService $userService, array $assignments)
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
    protected function addPolicy(Role $role, RoleService $roleService, array $policy)
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
}

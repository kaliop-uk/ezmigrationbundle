<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\User\User;
use Kaliop\eZMigrationBundle\API\Collection\UserCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\Core\Matcher\UserGroupMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\UserMatcher;

/**
 * Handles user migrations.
 */
class UserManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('user');
    protected $supportedActions = array('create', 'load', 'update', 'delete');

    protected $userMatcher;

    protected $userGroupMatcher;

    public function __construct(UserMatcher $userMatcher, UserGroupMatcher $userGroupMatcher)
    {
        $this->userMatcher = $userMatcher;
        $this->userGroupMatcher = $userGroupMatcher;
    }

    /**
     * Creates a user based on the DSL instructions.
     *
     * @todo allow setting extra profile attributes!
     */
    protected function create($step)
    {
        if (!isset($step->dsl['groups'])) {
            throw new InvalidStepDefinitionException('No user groups set to create user in.');
        }

        if (!is_array($step->dsl['groups'])) {
            $step->dsl['groups'] = array($step->dsl['groups']);
        }

        $userService = $this->repository->getUserService();
        $contentTypeService = $this->repository->getContentTypeService();

        $userGroups = array();
        foreach ($step->dsl['groups'] as $groupId) {
            $groupId = $this->resolveReference($groupId);
            $userGroup = $this->userGroupMatcher->matchOneByKey($groupId);

            // q: in which case can we have no group? And should we throw an exception?
            //if ($userGroup) {
                $userGroups[] = $userGroup;
            //}
        }

        $userContentType = $contentTypeService->loadContentTypeByIdentifier($this->getUserContentType($step));

        $userCreateStruct = $userService->newUserCreateStruct(
            $this->resolveReference($step->dsl['username']),
            $this->resolveReference($step->dsl['email']),
            $this->resolveReference($step->dsl['password']),
            $this->getLanguageCode($step),
            $userContentType
        );
        $userCreateStruct->setField('first_name', $this->resolveReference($step->dsl['first_name']));
        $userCreateStruct->setField('last_name', $this->resolveReference($step->dsl['last_name']));

        // Create the user
        $user = $userService->createUser($userCreateStruct, $userGroups);

        if (isset($step->dsl['roles'])) {
            $roleService = $this->repository->getRoleService();
            // we support both Ids and Identifiers
            foreach ($step->dsl['roles'] as $roleId) {
                $roleId = $this->resolveReference($roleId);
                $role = $this->roleMatcher->matchOneByKey($roleId);
                $roleService->assignRoleToUser($role, $user);
            }
        }

        $this->setReferences($user, $step);

        return $user;
    }

    protected function load($step)
    {
        $userCollection = $this->matchUsers('load', $step);

        $this->validateResultsCount($userCollection, $step);

        $this->setReferences($userCollection, $step);

        return $userCollection;
    }

    /**
     * Method to handle the update operation of the migration instructions
     *
     * @todo allow setting extra profile attributes!
     */
    protected function update($step)
    {
        $userCollection = $this->matchUsers('user', $step);

        $this->validateResultsCount($userCollection, $step);

        if (count($userCollection) > 1 && isset($step->dsl['email'])) {
            throw new MigrationBundleException("Can not execute User update because multiple users match, and an email section is specified in the dsl.");
        }

        $userService = $this->repository->getUserService();

        foreach ($userCollection as $key => $user) {

            $userUpdateStruct = $userService->newUserUpdateStruct();

            if (isset($step->dsl['email'])) {
                $userUpdateStruct->email = $this->resolveReference($step->dsl['email']);
            }
            if (isset($step->dsl['password'])) {
                $userUpdateStruct->password = (string)$this->resolveReference($step->dsl['password']);
            }
            if (isset($step->dsl['enabled'])) {
                $userUpdateStruct->enabled = $this->resolveReference($step->dsl['enabled']);
            }

            $user = $userService->updateUser($user, $userUpdateStruct);

            if (isset($step->dsl['groups'])) {
                $groups = $step->dsl['groups'];

                if (!is_array($groups)) {
                    $groups = array($groups);
                }

                $assignedGroups = $userService->loadUserGroupsOfUser($user);

                $targetGroupIds = [];
                // Assigning new groups to the user
                foreach ($groups as $groupToAssignId) {
                    $groupId = $this->resolveReference($groupToAssignId);
                    $groupToAssign = $this->userGroupMatcher->matchOneByKey($groupId);
                    $targetGroupIds[] = $groupToAssign->id;

                    $present = false;
                    foreach ($assignedGroups as $assignedGroup) {
                        // Make sure we assign the user only to groups he isn't already assigned to
                        if ($assignedGroup->id == $groupToAssign->id) {
                            $present = true;
                            break;
                        }
                    }
                    if (!$present) {
                        $userService->assignUserToUserGroup($user, $groupToAssign);
                    }
                }

                // Unassigning groups that are not in the list in the migration
                foreach ($assignedGroups as $assignedGroup) {
                    if (!in_array($assignedGroup->id, $targetGroupIds)) {
                        $userService->unAssignUserFromUserGroup($user, $assignedGroup);
                    }
                }
            }

            $userCollection[$key] = $user;
        }

        $this->setReferences($userCollection, $step);

        return $userCollection;
    }

    /**
     * Method to handle the delete operation of the migration instructions
     */
    protected function delete($step)
    {
        $userCollection = $this->matchUsers('delete', $step);

        $this->validateResultsCount($userCollection, $step);

        $this->setReferences($userCollection, $step);

        $userService = $this->repository->getUserService();

        foreach ($userCollection as $user) {
            $userService->deleteUser($user);
        }

        return $userCollection;
    }

    /**
     * @param string $action
     * @return UserCollection
     * @throws \Exception
     */
    protected function matchUsers($action, $step)
    {
        if (!isset($step->dsl['id']) && !isset($step->dsl['user_id']) && !isset($step->dsl['email']) && !isset($step->dsl['username']) && !isset($step->dsl['match'])) {
            throw new InvalidStepDefinitionException("The id, email or username of a user or a match condition is required to $action it");
        }

        // Backwards compat
        if (isset($step->dsl['match'])) {
            $match = $step->dsl['match'];
        } else {
            $conds = array();
            if (isset($step->dsl['id'])) {
                $conds['id'] = $step->dsl['id'];
            }
            if (isset($step->dsl['user_id'])) {
                $conds['id'] = $step->dsl['user_id'];
            }
            if (isset($step->dsl['email'])) {
                $conds['email'] = $step->dsl['email'];
            }
            if (isset($step->dsl['username'])) {
                $conds['login'] = $step->dsl['username'];
            }
            $match = $conds;
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($match);

        $tolerateMisses = isset($step->dsl['match_tolerate_misses']) ? $this->resolveReference($step->dsl['match_tolerate_misses']) : false;

        return $this->userMatcher->match($match, $tolerateMisses);
    }

    /**
     * @param User $user
     * @param array $references the definitions of the references to set
     * @throws InvalidStepDefinitionException
     * @return array key: the reference names, values: the reference values
     *
     * @todo allow setting refs to all the attributes that can be gotten for Contents
     */
    protected function getReferencesValues($user, array $references, $step)
    {
        $refs = array();

        foreach ($references as $key => $reference) {

            $reference = $this->parseReferenceDefinition($key, $reference);

            switch ($reference['attribute']) {
                case 'user_id':
                case 'id':
                    $value = $user->id;
                    break;
                case 'email':
                    $value = $user->email;
                    break;
                case 'enabled':
                    $value = $user->enabled;
                    break;
                case 'login':
                    $value = $user->login;
                    break;
                case 'groups_ids':
                    $value = [];
                    $userService = $this->repository->getUserService();
                    $userGroups = $userService->loadUserGroupsOfUser($user);
                    foreach ($userGroups as $userGroup) {
                        $value[] = $userGroup->id;
                    }
                    break;
                default:
                    throw new InvalidStepDefinitionException('User Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }
}

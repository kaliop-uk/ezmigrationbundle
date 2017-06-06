<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\UserCollection;
use Kaliop\eZMigrationBundle\Core\Matcher\UserGroupMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\UserMatcher;

/**
 * Handles user migrations.
 */
class UserManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('user');

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
            throw new \Exception('No user groups set to create user in.');
        }

        if (!is_array($step->dsl['groups'])) {
            $step->dsl['groups'] = array($step->dsl['groups']);
        }

        $userService = $this->repository->getUserService();
        $contentTypeService = $this->repository->getContentTypeService();

        $userGroups = array();
        foreach ($step->dsl['groups'] as $groupId) {
            $groupId = $this->referenceResolver->resolveReference($groupId);
            $userGroup = $this->userGroupMatcher->matchOneByKey($groupId);

            // q: in which case can we have no group? And should we throw an exception?
            //if ($userGroup) {
                $userGroups[] = $userGroup;
            //}
        }

        // FIXME: Hard coding content type to user for now
        $userContentType = $contentTypeService->loadContentTypeByIdentifier($this->getUserContentType($step));

        $userCreateStruct = $userService->newUserCreateStruct(
            $step->dsl['username'],
            $step->dsl['email'],
            $step->dsl['password'],
            $this->getLanguageCode($step),
            $userContentType
        );
        $userCreateStruct->setField('first_name', $step->dsl['first_name']);
        $userCreateStruct->setField('last_name', $step->dsl['last_name']);

        // Create the user
        $user = $userService->createUser($userCreateStruct, $userGroups);

        $this->setReferences($user, $step);

        return $user;
    }

    /**
     * Method to handle the update operation of the migration instructions
     *
     * @todo allow setting extra profile attributes!
     */
    protected function update($step)
    {
        $userCollection = $this->matchUsers('user', $step);

        if (count($userCollection) > 1 && isset($step->dsl['references'])) {
            throw new \Exception("Can not execute User update because multiple user match, and a references section is specified in the dsl. References can be set when only 1 user matches");
        }

        if (count($userCollection) > 1 && isset($step->dsl['email'])) {
            throw new \Exception("Can not execute User update because multiple user match, and an email section is specified in the dsl.");
        }

        $userService = $this->repository->getUserService();

        foreach ($userCollection as $key => $user) {

            $userUpdateStruct = $userService->newUserUpdateStruct();

            if (isset($step->dsl['email'])) {
                $userUpdateStruct->email = $step->dsl['email'];
            }
            if (isset($step->dsl['password'])) {
                $userUpdateStruct->password = (string)$step->dsl['password'];
            }
            if (isset($step->dsl['enabled'])) {
                $userUpdateStruct->enabled = $step->dsl['enabled'];
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
                    $groupId = $this->referenceResolver->resolveReference($groupToAssignId);
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
            throw new \Exception("The id, email or username of a user or a match condition is required to $action it");
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

        return $this->userMatcher->match($match);
    }

    /**
     * Sets references to object attributes.
     *
     * The User manager currently only supports setting references to user_id.
     *
     * @param \eZ\Publish\API\Repository\Values\User\User|UserCollection $user
     * @throws \InvalidArgumentException when trying to set references to unsupported attributes
     * @return boolean
     */
    protected function setReferences($user, $step)
    {
        if (!array_key_exists('references', $step->dsl)) {
            return false;
        }

        $this->setReferencesCommon($user, $step);
        $user = $this->insureSingleEntity($user, $step);

        foreach ($step->dsl['references'] as $reference) {
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
                default:
                    throw new \InvalidArgumentException('User Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $overwrite = false;
            if (isset($reference['overwrite'])) {
                $overwrite = $reference['overwrite'];
            }
            $this->referenceResolver->addReference($reference['identifier'], $value, $overwrite);
        }

        return true;
    }
}

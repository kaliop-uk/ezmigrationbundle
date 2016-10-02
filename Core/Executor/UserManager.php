<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\UserCollection;
use Kaliop\eZMigrationBundle\Core\Matcher\UserMatcher;

/**
 * Handles user migrations.
 */
class UserManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('user');

    protected $userMatcher;

    public function __construct(UserMatcher $userMatcher)
    {
        $this->userMatcher = $userMatcher;
    }

    /**
     * Creates a user based on the DSL instructions.
     *
     * @todo allow setting extra profile attributes!
     */
    protected function create()
    {
        if (!isset($this->dsl['groups'])) {
            throw new \Exception('No user groups set to create user in.');
        }

        if (!is_array($this->dsl['groups'])) {
            $this->dsl['groups'] = array($this->dsl['groups']);
        }

        $userService = $this->repository->getUserService();
        $contentTypeService = $this->repository->getContentTypeService();

        $userGroups = array();
        foreach ($this->dsl['groups'] as $groupId) {
            $groupId = $this->resolveReferences($groupId);
            $userGroup = $userService->loadUserGroup($groupId);

            // q: in which case can we have no group? And should we throw an exception?
            if ($userGroup) {
                $userGroups[] = $userGroup;
            }
        }

        // FIXME: Hard coding content type to user for now
        $userContentType = $contentTypeService->loadContentTypeByIdentifier(self::USER_CONTENT_TYPE);

        $userCreateStruct = $userService->newUserCreateStruct(
            $this->dsl['username'],
            $this->dsl['email'],
            $this->dsl['password'],
            $this->getLanguageCode(),
            $userContentType
        );
        $userCreateStruct->setField('first_name', $this->dsl['first_name']);
        $userCreateStruct->setField('last_name', $this->dsl['last_name']);

        // Create the user
        $user = $userService->createUser($userCreateStruct, $userGroups);

        $this->setReferences($user);

        return $user;
    }

    /**
     * Method to handle the update operation of the migration instructions
     *
     * @todo allow setting extra profile attributes!
     */
    protected function update()
    {
        $userCollection = $this->matchUsers('user');

        if (count($userCollection) > 1 && isset($this->dsl['references'])) {
            throw new \Exception("Can not execute User update because multiple user match, and a references section is specified in the dsl. References can be set when only 1 user matches");
        }

        if (count($userCollection) > 1 && isset($this->dsl['email'])) {
            throw new \Exception("Can not execute User update because multiple user match, and an email section is specified in the dsl.");
        }

        $userService = $this->repository->getUserService();

        foreach($userCollection as $key => $user) {

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

            $user = $userService->updateUser($user, $userUpdateStruct);

            if (isset($this->dsl['groups'])) {
                if (!is_array($this->dsl['groups'])) {
                    $this->dsl['groups'] = array($this->dsl['groups']);
                }

                $assignedGroups = $userService->loadUserGroupsOfUser($user);

                // Assigning new groups to the user
                foreach ($this->dsl['groups'] as $groupToAssignId) {
                    $present = false;
                    foreach ($assignedGroups as $assignedGroup) {
                        // Make sure we assign the user only to groups he isn't already assigned to
                        if ($assignedGroup->id == $groupToAssignId) {
                            $present = true;
                            break;
                        }
                    }
                    if (!$present) {
                        $groupToAssign = $userService->loadUserGroup($groupToAssignId);
                        $userService->assignUserToUserGroup($user, $groupToAssign);
                    }
                }

                // Unassigning groups that are not in the list in the migration
                foreach ($assignedGroups as $assignedGroup) {
                    if (!in_array($assignedGroup->id, $this->dsl['groups'])) {
                        $userService->unAssignUserFromUserGroup($user, $assignedGroup);
                    }
                }
            }

            $userCollection[$key] = $user;
        }

        $this->setReferences($userCollection);

        return $userCollection;
    }

    /**
     * Method to handle the delete operation of the migration instructions
     */
    protected function delete()
    {
        $userCollection = $this->matchUsers('delete');

        $userService = $this->repository->getUserService();

        foreach($userCollection as $user) {
            $userService->deleteUser($user);
        }

        return $userCollection;
    }

    /**
     * @param string $action
     * @return RoleCollection
     * @throws \Exception
     */
    protected function matchUsers($action)
    {
        if (!isset($this->dsl['id']) && !isset($this->dsl['user_id']) && !isset($this->dsl['email']) && !isset($this->dsl['username']) && !isset($this->dsl['match'])) {
            throw new \Exception("The id, email or username of a user or a match condition is required to $action it.");
        }

        // Backwards compat
        if (!isset($this->dsl['match'])) {
            $conds = array();
            if (isset($this->dsl['id'])) {
                 $conds['id'] = $this->dsl['id'];
            }
            if (isset($this->dsl['user_id'])) {
                $conds['id'] = $this->dsl['user_id'];
            }
            if (isset($this->dsl['email'])) {
                $conds['email'] = $this->dsl['email'];
            }
            if (isset($this->dsl['username'])) {
                $conds['login'] = $this->dsl['username'];
            }
            $this->dsl['match'] = $conds;
        }

        $match = $this->dsl['match'];

        // convert the references passed in the match
        foreach ($match as $condition => $values) {
            if (is_array($values)) {
                foreach ($values as $position => $value) {
                    $match[$condition][$position] = $this->resolveReferences($value);
                }
            } else {
                $match[$condition] = $this->resolveReferences($values);
            }
        }

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
    protected function setReferences($user)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        if ($user instanceof UserCollection) {
            if (count($user) > 1) {
                throw new \InvalidArgumentException('User Manager does not support setting references for creating/updating of multiple users');
            }
            $user = reset($user);
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

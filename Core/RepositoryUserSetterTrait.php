<?php

namespace Kaliop\eZMigrationBundle\Core;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidUserAccountException;

/**
 * NB: needs a class member 'repository'
 */
trait RepositoryUserSetterTrait
{
    /**
     * Helper method to log in a user that can make changes to the system.
     * @param int|string|false $userLoginOrId a user login or user-id. If *false* is passed in, current user is not changed and false is returned
     * @return int|false id of the previously logged-in user. False when false is passed in.
     * @throws InvalidUserAccountException
     */
    protected function loginUser($userLoginOrId)
    {
        if ($userLoginOrId === false) {
            return false;
        }

        $previousUser = $this->repository->getCurrentUser();

        try {
            if (is_int($userLoginOrId)) {
                if ($userLoginOrId == $previousUser->id) {
                    return $previousUser->id;
                }
                $newUser = $this->repository->getUserService()->loadUser($userLoginOrId);
            } else {
                if ($userLoginOrId == $previousUser->login) {
                    return $previousUser->id;
                }
                $newUser = $this->repository->getUserService()->loadUserByLogin($userLoginOrId);
            }
        } catch (NotFoundException $e) {
            throw new InvalidUserAccountException("Could not find the required user account to be used for logging in: '$userLoginOrId'. UserService says: " . $e->getMessage(), $e->getCode(), $e);
        }

        $this->repository->setCurrentUser($newUser);

        return $previousUser->id;
    }
}

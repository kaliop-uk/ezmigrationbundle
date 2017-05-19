<?php

namespace Kaliop\eZMigrationBundle\Core;

use \eZ\Publish\API\Repository\Values\User\User;

/**
 * NB: needs a class member 'repository'
 */
trait RepositoryUserSetterTrait
{
    /**
     * Helper method to log in a user that can make changes to the system.
     * @param int|string|false $userLoginOrId a user login or user-id. If *false* is passed in, current user is not changed and false is returned
     * @return int|false id of the previously logged in user
     */
    protected function loginUser($userLoginOrId)
    {
        if ($userLoginOrId === false) {
            return false;
        }

        $previousUser = $this->repository->getCurrentUser();

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

        $this->repository->setCurrentUser($newUser);

        return $previousUser->id;
    }
}

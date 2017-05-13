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
     * @param int|string $userLoginOrId a user login or user-id
     * @return int id of the previously logged in user
     */
    protected function loginUser($userLoginOrId)
    {
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

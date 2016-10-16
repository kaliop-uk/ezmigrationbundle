<?php

namespace Kaliop\eZMigrationBundle\Core;

/**
 * NB: needs a class member 'repository'
 */
trait RepositoryUserSetterTrait
{
    /**
     * Helper method to log in a user that can make changes to the system.
     * @param int $userId
     * @return int id of the previously logged in user
     */
    protected function loginUser($userId)
    {
        $previousUser = $this->repository->getCurrentUser();

        if ($userId != $previousUser->id) {
            $this->repository->setCurrentUser($this->repository->getUserService()->loadUser($userId));
        }

        return $previousUser->id;
    }
}

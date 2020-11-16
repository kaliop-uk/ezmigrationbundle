<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use eZ\Publish\Core\Base\Exceptions\NotFoundException as CoreNotFoundException;
use eZ\Publish\API\Repository\Values\User\User;
use Kaliop\eZMigrationBundle\API\Collection\UserCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

class UserMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_USER_ID = 'user_id';
    const MATCH_USER_LOGIN = 'login';
    const MATCH_USER_EMAIL = 'email';
    const MATCH_USERGROUP_ID = 'user_group_id';

    protected $allowedConditions = array(
        self::MATCH_AND, self::MATCH_OR,
        self::MATCH_USER_ID, self::MATCH_USER_LOGIN, self::MATCH_USER_EMAIL,
        self::MATCH_USERGROUP_ID,
        // aliases
        'id',
        // BC
        'usergroup_id'
    );
    protected $returns = 'User';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return UserCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function match(array $conditions, $tolerateMisses = false)
    {
        return $this->matchUser($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return UserCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function matchUser(array $conditions, $tolerateMisses = false)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_USER_ID:
                   return new UserCollection($this->findUsersById($values, $tolerateMisses));

                case self::MATCH_USER_LOGIN:
                    return new UserCollection($this->findUsersByLogin($values, $tolerateMisses));

                case 'usergroup_id':
                case self::MATCH_USERGROUP_ID:
                    return new UserCollection($this->findUsersByGroup($values, $tolerateMisses));

                case self::MATCH_USER_EMAIL:
                    return new UserCollection($this->findUsersByEmail($values, $tolerateMisses));

                case self::MATCH_AND:
                    return $this->matchAnd($values, $tolerateMisses);

                case self::MATCH_OR:
                    return $this->matchOr($values, $tolerateMisses);
            }
        }
    }

    /**
     * NB: bad luck if user login contains an email or otherwise @ character
     * @param string $key
     * @return array
     */
    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_USER_ID => $key);
        }
        if (strpos($key, '@') !== false) {
            return array(self::MATCH_USER_EMAIL => $key);
        }
        return array(self::MATCH_USER_LOGIN => $key);
    }

    /**
     * @param int[] $userIds
     * @param bool $tolerateMisses
     * @return User[]
     * @throws NotFoundException
     */
    protected function findUsersById(array $userIds, $tolerateMisses = false)
    {
        $users = [];

        foreach ($userIds as $userId) {
            try{
                // return unique contents
                $user = $this->repository->getUserService()->loadUser($userId);

                $users[$user->id] = $user;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $users;
    }

    /**
     * @param string[] $logins
     * @param bool $tolerateMisses
     * @return User[]
     * @throws NotFoundException
     */
    protected function findUsersByLogin(array $logins, $tolerateMisses = false)
    {
        $users = [];

        foreach ($logins as $login) {
            try {
                // return unique contents
                $user = $this->repository->getUserService()->loadUserByLogin($login);

                $users[$user->id] = $user;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $users;
    }

    /**
     * @param string[] $emails
     * @param bool $tolerateMisses
     * @return User[]
     * @throws NotFoundException
     */
    protected function findUsersByEmail(array $emails, $tolerateMisses = false)
    {
        $users = [];

        foreach ($emails as $email) {
            // return unique contents
            $matches = $this->repository->getUserService()->loadUsersByEmail($email);
            if (!$matches && !$tolerateMisses) {
                throw new CoreNotFoundException("User", $email);
            }
            foreach ($matches as $user) {
                $users[$user->id] = $user;
            }
        }

        return $users;
    }

    /**
     * @param array $groupsIds
     * @param false $tolerateMisses
     * @return array
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    protected function findUsersByGroup(array $groupsIds, $tolerateMisses = false)
    {
        $users = [];

        foreach ($groupsIds as $groupId) {

            try {
                $group = $this->repository->getUserService()->loadUserGroup($groupId);
            } catch(NotFoundException $e) {
                if ($tolerateMisses) {
                    continue;
                } else {
                    throw $e;
                }
            }

            $offset = 0;
            $limit = 100;
            do {
                $matches = $this->repository->getUserService()->loadUsersOfUserGroup($group, $offset, $limit);
                $offset += $limit;

                // return unique contents
                foreach ($matches as $user) {
                    $users[$user->id] = $user;
                }

            } while (count($matches));
        }

        return $users;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\User\User;
use Kaliop\eZMigrationBundle\API\Collection\UserCollection;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

class UserMatcher extends AbstractMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_USER_ID = 'user_id';
    const MATCH_USER_LOGIN = 'login';
    const MATCH_USER_EMAIL = 'email';

    protected $allowedConditions = array(
        self::MATCH_USER_ID, self::MATCH_USER_LOGIN, self::MATCH_USER_EMAIL,
        // aliases
        'id'
    );
    protected $returns = 'User';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return UserCollection
     */
    public function match(array $conditions)
    {
        return $this->matchUser($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return UserCollection
     */
    public function matchUser(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_USER_ID:
                   return new UserCollection($this->findUsersById($values));

                case self::MATCH_USER_LOGIN:
                    return new UserCollection($this->findUsersByLogin($values));

                case self::MATCH_USER_EMAIL:
                    return new UserCollection($this->findUsersByEmail($values));
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
     * @return User[]
     */
    protected function findUsersById(array $userIds)
    {
        $users = [];

        foreach ($userIds as $userId) {
            // return unique contents
            $user = $this->repository->getUserService()->loadUser($userId);

            $users[$user->id] = $user;
        }

        return $users;
    }

    /**
     * @param string[] $logins
     * @return User[]
     */
    protected function findUsersByLogin(array $logins)
    {
        $users = [];

        foreach ($logins as $login) {
            // return unique contents
            $user = $this->repository->getUserService()->loadUserByLogin($login);

            $users[$user->id] = $user;
        }

        return $users;
    }

    /**
     * @param string[] $emails
     * @return User[]
     *
     * @todo check if this fails when user is not found
     */
    protected function findUsersByEmail(array $emails)
    {
        $users = [];

        foreach ($emails as $email) {
            // return unique contents
            $matches = $this->repository->getUserService()->loadUsersByEmail($email);

            foreach ($matches as $user) {
                $users[$user->id] = $user;
            }
        }

        return $users;
    }
}

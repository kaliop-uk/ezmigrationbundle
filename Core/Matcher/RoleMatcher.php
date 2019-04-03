<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\User\Role;
use Kaliop\eZMigrationBundle\API\Collection\RoleCollection;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;

class RoleMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_ROLE_ID = 'role_id';
    const MATCH_ROLE_IDENTIFIER = 'role_identifier';

    protected $allowedConditions = array(
        self::MATCH_ALL, self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_ROLE_ID, self::MATCH_ROLE_IDENTIFIER,
        // aliases
        'id', 'identifier'
    );
    protected $returns = 'Role';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return RoleCollection
     * @throws InvalidMatchConditionsException
     */
    public function match(array $conditions)
    {
        return $this->matchRole($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return RoleCollection
     * @throws InvalidMatchConditionsException
     */
    public function matchRole(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_ROLE_ID:
                   return new RoleCollection($this->findRolesById($values));

                case 'identifier':
                case self::MATCH_ROLE_IDENTIFIER:
                    return new RoleCollection($this->findRolesByIdentifier($values));

                case self::MATCH_ALL:
                    return new RoleCollection($this->findAllRoles());

                case self::MATCH_AND:
                    return $this->matchAnd($values);

                case self::MATCH_OR:
                    return $this->matchOr($values);

                case self::MATCH_NOT:
                    return new RoleCollection(array_diff_key($this->findAllRoles(), $this->matchRole($values)->getArrayCopy()));
            }
        }
    }

    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_ROLE_ID => $key);
        }
        return array(self::MATCH_ROLE_IDENTIFIER => $key);
    }

    /**
     * @param int[] $roleIds
     * @return Role[]
     */
    protected function findRolesById(array $roleIds)
    {
        $roles = [];

        foreach ($roleIds as $roleId) {
            // return unique contents
            $role = $this->repository->getRoleService()->loadRole($roleId);
            $roles[$role->id] = $role;
        }

        return $roles;
    }

    /**
     * @param string[] $roleIdentifiers
     * @return Role[]
     */
    protected function findRolesByIdentifier(array $roleIdentifiers)
    {
        $roles = [];

        foreach ($roleIdentifiers as $roleIdentifier) {
            // return unique contents
            $role = $this->repository->getRoleService()->loadRoleByIdentifier($roleIdentifier);
            $roles[$role->id] = $role;
        }

        return $roles;
    }

    /**
     * @return Role[]
     */
    protected function findAllRoles()
    {
        $roles = [];

        foreach ($this->repository->getRoleService()->loadRoles() as $role) {
            // return unique contents
            $roles[$role->id] = $role;
        }

        return $roles;
    }
}

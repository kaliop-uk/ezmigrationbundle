<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
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
     * @param bool $tolerateMisses
     * @return RoleCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function match(array $conditions, $tolerateMisses = false)
    {
        return $this->matchRole($conditions, $tolerateMisses);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return RoleCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function matchRole(array $conditions, $tolerateMisses = false)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_ROLE_ID:
                   return new RoleCollection($this->findRolesById($values, $tolerateMisses));

                case 'identifier':
                case self::MATCH_ROLE_IDENTIFIER:
                    return new RoleCollection($this->findRolesByIdentifier($values, $tolerateMisses));

                case self::MATCH_ALL:
                    return new RoleCollection($this->findAllRoles());

                case self::MATCH_AND:
                    return $this->matchAnd($values, $tolerateMisses);

                case self::MATCH_OR:
                    return $this->matchOr($values, $tolerateMisses);

                case self::MATCH_NOT:
                    return new RoleCollection(array_diff_key($this->findAllRoles(), $this->matchRole($values, true)->getArrayCopy()));
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
     * @param bool $tolerateMisses
     * @return Role[]
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    protected function findRolesById(array $roleIds, $tolerateMisses = false)
    {
        $roles = [];

        foreach ($roleIds as $roleId) {
            try {
                // return unique contents
                $role = $this->repository->getRoleService()->loadRole($roleId);
                $roles[$role->id] = $role;
            /// @todo should we survive as well UnauthorizedException ? It seems to be a different kind of error than non-existing roles...
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $roles;
    }

    /**
     * @param string[] $roleIdentifiers
     * @param bool $tolerateMisses
     * @return Role[]
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    protected function findRolesByIdentifier(array $roleIdentifiers, $tolerateMisses = false)
    {
        $roles = [];

        foreach ($roleIdentifiers as $roleIdentifier) {
            try {
                // return unique contents
                $role = $this->repository->getRoleService()->loadRoleByIdentifier($roleIdentifier);
                $roles[$role->id] = $role;
            /// @todo should we survive as well UnauthorizedException ? It seems to be a different kind of error than non-existing roles...
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
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

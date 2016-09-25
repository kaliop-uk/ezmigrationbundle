<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\User\Role;
use Kaliop\eZMigrationBundle\API\Collection\RoleCollection;

class RoleMatcher extends AbstractMatcher
{
    const MATCH_ROLE_ID = 'id';
    const MATCH_ROLE_IDENTIFIER = 'identifier';

    protected $allowedConditions = array(
        self::MATCH_ROLE_ID, self::MATCH_ROLE_IDENTIFIER
    );
    protected $returns = 'Role';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return RoleCollection
     */
    public function match(array $conditions)
    {
        return $this->matchRole($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return RoleCollection
     */
    public function matchRole(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case self::MATCH_ROLE_ID:
                   return new RoleCollection($this->findRolesById($values));

                case self::MATCH_ROLE_IDENTIFIER:
                    return new RoleCollection($this->findRolesByIdentifier($values));
            }
        }
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
}

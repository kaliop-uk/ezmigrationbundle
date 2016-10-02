<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Used for those reference resolvers which can have their references injected
 */
interface ReferenceBagInterface
{
    /**
     * Add a reference to be retrieved later.
     *
     * @param string $identifier The identifier of the reference
     * @param mixed $value The value of the reference
     * @return bool true if the reference is accepted by this resolver, otherwise false
     * @throws \Exception When there is a reference with the specified $identifier already.
     */
    public function addReference($identifier, $value);
}

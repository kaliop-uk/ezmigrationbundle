<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Used to identify those reference resolvers which can have references injected.
 * Most of the time one would implement directly ReferenceResolverBagInterface.
 */
interface ReferenceBagInterface
{
    /**
     * Adds a reference to be retrieved later.
     *
     * @param string $identifier The identifier of the reference
     * @param mixed $value The value of the reference
     * @param bool $overwrite do overwrite the existing ref if it exist without raising an exception
     * @return bool true if the reference is accepted by this resolver, otherwise false
     * @throws \Exception When there is a reference with the specified $identifier already.
     */
    public function addReference($identifier, $value, $overwrite = false);
}

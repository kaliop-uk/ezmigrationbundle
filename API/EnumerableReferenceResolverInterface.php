<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Used to identify those reference resolvers which can enumerate their references injected.
 */
interface EnumerableReferenceResolverInterface
{
    /**
     * Lists all existing references
     *
     * @return array key: ref identifier, value: reference value
     */
    public function listReferences();
}

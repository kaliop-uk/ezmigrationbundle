<?php

namespace Kaliop\eZMigrationBundle\API;

interface MatcherInterface
{
    /**
     * Returns an array of items, or an array-like object, whith all items which satisfy the conditions $conditions
     *
     * @param array $conditions
     * @return array|ArrayObject
     */
    public function match(array $conditions);
}

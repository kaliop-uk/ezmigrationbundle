<?php

namespace Kaliop\eZMigrationBundle\API;

interface MatcherInterface
{
    const MATCH_ALL = 'all';
    const MATCH_NOT = 'not';
    const MATCH_AND = 'and';
    const MATCH_OR = 'or';

    /**
     * Returns an array of items, or an array-like object, with all items which satisfy the conditions $conditions
     *
     * @param array $conditions
     * @return array|\ArrayObject
     */
    public function match(array $conditions);

    /**
     * Like match, but will throw an exception if there are 0 or more than 1 items matching
     *
     * @param array $conditions
     * @return mixed
     * @throws \Exception
     */
    public function matchOne(array $conditions);
}

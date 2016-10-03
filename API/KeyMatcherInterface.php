<?php

namespace Kaliop\eZMigrationBundle\API;

interface KeyMatcherInterface extends MatcherInterface
{
    /**
     * Matches *one* item based on a unique key (id, identifier, etc... as long as it is a scalar value)
     * @see MatcherInterface
     *
     * @param string|int $key
     * @return mixed
     * @throws \Exception
     */
    public function matchOneByKey($key);
}

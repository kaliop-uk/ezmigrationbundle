<?php

namespace Kaliop\eZMigrationBundle\API;

interface KeyMatcherInterface
{
    /**
     * Matches one item based on a unique key (id, identifier etc...)
     *
     * @param string|int $key
     * @return mixed
     * @throws \Exception
     */
    public function matchByKey($key);
}

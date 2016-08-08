<?php

namespace Kaliop\eZMigrationBundle\API;

interface MatcherInterface
{
    /**
     * @param array $conditions
     * @return mixed
     */
    public function match(array $conditions);
}
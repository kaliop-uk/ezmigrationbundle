<?php

namespace Kaliop\eZMigrationBundle\API;

interface EnumerableMatcherInterface
{
    /**
     * Returns the list of the valid keys to be used in conditions arrays
     * @return string[]
     */
    public function listAllowedConditions();
}

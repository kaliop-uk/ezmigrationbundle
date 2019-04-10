<?php

namespace Kaliop\eZMigrationBundle\API;

use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;

/**
 * Interface that all migration definition handlers need to implement if they can generate a migration definition.
 */
interface MigrationGeneratorInterface
{
    /**
     * Generates a migration definition in array format
     *
     * @param array $matchCondition
     * @param string $mode
     * @param array $context
     * @return array Migration data
     * @throws InvalidMatchConditionsException
     * @throws \Exception
     */
    public function generateMigration(array $matchCondition, $mode, array $context = array());
}

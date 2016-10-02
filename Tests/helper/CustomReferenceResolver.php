<?php

namespace Kaliop\eZMigrationBundle\Tests\helper;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

/**
 * Does nothing for the moment, except making sure that it can be injected correctly via a tagged service
 */
class CustomReferenceResolver implements ReferenceResolverInterface
{
    public function isReference($stringIdentifier)
    {
        return $stringIdentifier === 'test_custom_reference_with_low_chances_of_collision_with_random_data';
    }

    /**
     * Return the id of an existing location
     * @param string $stringIdentifier
     * @return mixed
     * @throws \Exception if reference with given Identifier is not found
     */
    public function getReferenceValue($stringIdentifier)
    {
        return 2;
    }
}

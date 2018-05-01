<?php

namespace Kaliop\eZMigrationBundle\Tests\helper;

use Kaliop\eZMigrationBundle\Core\ReferenceResolver\PrefixBasedResolverInterface;
use Kaliop\eZMigrationBundle\API\EnumerableReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\EmbeddedReferenceResolverInterface;

/**
 * Does nothing for the moment, except making sure that it can be injected correctly via a tagged service
 */
class CustomReferenceResolver implements PrefixBasedResolverInterface, EnumerableReferenceResolverInterface, EmbeddedReferenceResolverInterface
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

    public function resolveReference($stringIdentifier)
    {
        return $this->isReference($stringIdentifier) ? $this->getReferenceValue($stringIdentifier) : $stringIdentifier;
    }

    public function getRegexp()
    {
        return '';
    }

    public function listReferences()
    {
        return array();
    }

    public function hasEmbeddedReferences($string)
    {
        return (strpos($string, '[test_custom_reference_with_low_chances_of_collision_with_random_data]') !== false);
    }

    public function resolveEmbeddedReferences($string)
    {
        return (str_replace('[test_custom_reference_with_low_chances_of_collision_with_random_data]', '2', $string));
    }
}

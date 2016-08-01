<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

class ChainResolver implements ReferenceResolverInterface
{
    /** @var ReferenceResolverInterface[] $resolvers */
    protected $resolvers = array();

    /**
     * @param string $stringIdentifier
     * @return bool
     */
    public function isReference($stringIdentifier)
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->isReference($stringIdentifier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $stringIdentifier
     * @return mixed
     */
    public function getReferenceValue($stringIdentifier)
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->isReference($stringIdentifier)) {
                return true;
            }
        }

        throw \Exception("Could not resolve reference with identifier: '$stringIdentifier'");
    }
}
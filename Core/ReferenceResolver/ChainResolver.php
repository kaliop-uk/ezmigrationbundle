<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\ReferenceBagInterface;

class ChainResolver implements ReferenceResolverInterface, ReferenceBagInterface
{
    /** @var ReferenceResolverInterface[] $resolvers */
    protected $resolvers = array();

    /**
     * @param ReferenceResolverInterface[] $resolvers
     */
    public function __construct(array $resolvers)
    {
        $this->resolvers = $resolvers;
    }

    public function addResolver(ReferenceResolverInterface $resolver)
    {
        $this->resolvers[] = $resolver;
    }

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
                return $resolver->getReferenceValue($stringIdentifier);
            }
        }

        throw \Exception("Could not resolve reference with identifier: '$stringIdentifier'");
    }

    /**
     * Tries to add the reference to one of the resolvers in the chain (the first accepting it)
     *
     * @param string $identifier
     * @param mixed $value
     * @return bool
     */
    public function addReference($identifier, $value)
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver instanceof ReferenceBagInterface) {
                if ($resolver->addReference($identifier, $value)) {
                    return true;
                }
            }
        }

        return false;
    }
}

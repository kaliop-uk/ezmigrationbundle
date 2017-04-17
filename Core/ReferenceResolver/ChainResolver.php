<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\ReferenceBagInterface;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;

class ChainResolver implements ReferenceResolverBagInterface
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
     * @throws \Exception
     */
    public function getReferenceValue($stringIdentifier)
    {
        $resolvedOnce = false;

        foreach ($this->resolvers as $resolver) {
            if ($resolver->isReference($stringIdentifier)) {
                $stringIdentifier = $resolver->getReferenceValue($stringIdentifier);
                $resolvedOnce = true;
            }
        }

        if (!$resolvedOnce) {
            throw new \Exception("Could not resolve reference with identifier: '$stringIdentifier'");
        }

        return $stringIdentifier;
    }

    public function resolveReference($stringIdentifier)
    {
        if ($this->isReference($stringIdentifier)) {
            return $this->getReferenceValue($stringIdentifier);
        }
        return $stringIdentifier;
    }

    /**
     * Tries to add the reference to one of the resolvers in the chain (the first accepting it)
     *
     * @param string $identifier
     * @param mixed $value
     * @param bool $overwrite do overwrite the existing ref if it exist without raising an exception
     * @return bool
     */
    public function addReference($identifier, $value, $overwrite = false)
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver instanceof ReferenceBagInterface) {
                if ($resolver->addReference($identifier, $value, $overwrite)) {
                    return true;
                }
            }
        }

        return false;
    }
}

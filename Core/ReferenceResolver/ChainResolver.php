<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\ReferenceBagInterface;
use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\EnumerableReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\EmbeddedReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Exception\ReferenceUnresolvedException;

class ChainResolver implements ReferenceResolverBagInterface, EnumerableReferenceResolverInterface, EmbeddedReferenceResolverInterface
{
    /** @var ReferenceResolverInterface[] $resolvers */
    protected $resolvers = array();
    /** @var bool $doResolveEmbeddedReferences */
    protected $doResolveEmbeddedReferences;

    /**
     * @param ReferenceResolverInterface[] $resolvers
     * @param bool $resolveEmbeddedReferences decides wheter partial/embedded refs are resolved by default or not
     */
    public function __construct(array $resolvers, $resolveEmbeddedReferences = true)
    {
        $this->resolvers = $resolvers;
        $this->doResolveEmbeddedReferences = $resolveEmbeddedReferences;
    }

    public function addResolver(ReferenceResolverInterface $resolver)
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * NB: does *not* check for embedded refs, even when $this->>doResolveEmbeddedReferences is true
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
     * @throws ReferenceUnresolvedException
     */
    public function getReferenceValue($stringIdentifier)
    {
        $resolvedOnce = false;

        foreach ($this->resolvers as $resolver) {
            if ($resolver->isReference($stringIdentifier)) {
                $stringIdentifier = $resolver->getReferenceValue($stringIdentifier);
                // In case of many resolvers resolving the same ref, the first one wins, but we allow recursive resolving
                $resolvedOnce = true;
            }
        }

        if (!$resolvedOnce) {
            throw new ReferenceUnresolvedException("Could not resolve reference with identifier: '$stringIdentifier'");
        }

        return $stringIdentifier;
    }

    public function resolveReference($stringIdentifier)
    {
        if ($this->doResolveEmbeddedReferences && is_string($stringIdentifier)) {
            $stringIdentifier = $this->resolveEmbeddedReferences($stringIdentifier);
        }

        // for speed, we avoid calling isReference() and call directly getReferenceValue()
        try {
            return $this->getReferenceValue($stringIdentifier);
        } catch (ReferenceUnresolvedException $e) {
            return $stringIdentifier;
        }
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

    public function listReferences()
    {
        $refs = array();

        foreach ($this->resolvers as $resolver) {
            if (! $resolver instanceof EnumerableReferenceResolverInterface) {
                throw new \Exception("Could not enumerate references because of chained resolver of type: " . get_class($resolver));
            }

            // later resolvers are stronger (see getReferenceValue)
            $refs = array_merge($refs, $resolver->listReferences());
        }

        return $refs;
    }

    /**
     * @param string $string
     * @return bool true if the given $stringIdentifier contains at least one occurrence of the reference(s)
     * @throws \Exception if any resolver in the chain is not an EmbeddedReferenceResolverInterface
     */
    public function hasEmbeddedReferences($string)
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver instanceof EmbeddedReferenceResolverInterface) {
                if ($resolver->hasEmbeddedReferences($string)) {
                    return true;
                }
            } else {
                // NB: BC break for 4.8... should be enabled for 5.0 ?
                //throw new \Exception("Could not verify embedded references because of chained resolver of type: " . get_class($resolver));
            }
        }

        return false;
    }

    /**
     * Returns the $string with eventual refs resolved.
     *
     * @param string $string
     * @return string
     * @throws \Exception if any resolver in the chain is not an EmbeddedReferenceResolverInterface
     */
    public function resolveEmbeddedReferences($string)
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver instanceof EmbeddedReferenceResolverInterface) {
                $string = $resolver->resolveEmbeddedReferences($string);
            } else {
                // NB: BC break for 4.8... should be enabled for 5.0 ?
                //throw new \Exception("Could not resolve embedded references because of chained resolver of type: " . get_class($resolver));
            }
        }

        return $string;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\API\Handler;

use Kaliop\eZMigrationBundle\Core\API\LocationResolver\LocationResolverInterface;

class LocationResolverHandler
{
    /**
     * @var LocationResolverInterface[]
     */
    private $resolvers;

    /**
     * @param LocationResolverInterface $resolver
     */
    public function addResolver(LocationResolverInterface $resolver)
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * @param string $identifier
     * @return int
     */
    public function resolve($identifier)
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->shouldResolve($identifier)) {
                return $resolver->resolve($identifier);
            }
        }

        return $identifier;
    }
}

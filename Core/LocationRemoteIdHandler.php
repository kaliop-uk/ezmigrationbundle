<?php

namespace Kaliop\eZMigrationBundle\Core;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handle references to location remote ID's.
 */
class LocationRemoteIdHandler
{
    /**
     * Constant defining the prefix for all reference identifier strings in definitions
     */
    const REFERENCE_PREFIX = 'location:';

    /**
     * Array of all references set by the currently running migrations.
     *
     * @var array
     */
    private $locations = array();

    /**
     * The instance of the ReferenceHandler
     * @var ReferenceHandler
     */
    private static $instance;

    /**
     * Private constructor as this is a singleton.
     */
    private function __construct()
    {
    }

    /**
     * Get the ReferenceHandler instance
     *
     * @return LocationRemoteIdHandler
     */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new LocationRemoteIdHandler();
        }

        return self::$instance;
    }

    /**
     * Get a location id from the remote id supplied. You must pass the container object through to get access
     * to the location service
     *
     * @param string $identifier
     * @param ContainerInterface $container
     * @return mixed
     * @throws \Exception When trying to retrieve a location that doesn't exist or the user is unauthorised
     */
    public function getLocationId($identifier, ContainerInterface $container)
    {
        $repository = $container->get('ezpublish.api.repository');
        $location = $repository->getLocationService()->loadLocationByRemoteId($identifier);

        return $location->id;
    }
}

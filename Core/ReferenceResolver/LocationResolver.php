<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use \eZ\Publish\API\Repository\Repository;

/**
 * Handles references to locations. At the moment: supports remote Ids.
 *
 * @todo drop support for 'location:' in favour of
 */
class LocationResolver extends AbstractResolver
{
    /**
     * Defines the prefix for all reference identifier strings in definitions
     */
    protected $referencePrefixes = array('location:' /*, 'location_remote_id:'*/);

    protected $repository;

    /**
     * @param Repository $repository
     */
    public function __construct(Repository $repository)
    {
        parent::__construct();

        $this->repository = $repository;
    }

    /**
     * @param $stringIdentifier location_remote_id:<remote_id>
     * @return string Location id
     * @throws \Exception
     */
    public function getReferenceValue($stringIdentifier)
    {
        $ref = $this->getReferenceIdentifierByPrefix($stringIdentifier);
        switch($ref['prefix']) {
            // deprecated tag: 'location:'
            case 'location:':
                return $this->repository->getLocationService()->loadLocationByRemoteId($ref['identifier'])->id;
            //case 'location_remote_id:':
            //    return $this->repository->getLocationService()->loadLocationByRemoteId($ref['identifier'])->id;
        }
    }
}

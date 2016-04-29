<?php

namespace Kaliop\eZMigrationBundle\Core\API\LocationResolver;

use eZ\Publish\API\Repository\Values\Content\Location;

class LocationRemoteIdLocationResolver extends AbstractRepositoryAwareLocationResolver
{
    /**
     * @var string
     */
    private $remoteId;

    /**
     * Resolves reference to location id
     *
     * @param $reference
     * @return int
     * @throws \Exception
     */
    public function resolve($reference)
    {
        if (empty($this->remoteId)) {
            throw new \Exception('Remote Id was not found, first check if reference should be resolved');
        }

        $location = $this->repository->getLocationService()->loadLocationByRemoteId($this->remoteId);

        if (!$location instanceof Location) {
            throw new \Exception(sprintf('Location with remote id "%s" not found', $this->remoteId));
        }

        return $location->id;
    }

    /**
     * Tests if $reference should be resolved to location id
     *
     * @param $reference
     * @return bool
     */
    public function shouldResolve($reference)
    {
        if (preg_match('/^location_remote_id:(.*)$/', $reference, $match)) {
            $this->remoteId = $match[1];

            return true;
        }

        return false;
    }
}

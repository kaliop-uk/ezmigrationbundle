<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use Kaliop\eZMigrationBundle\API\Collection\LocationCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidSortConditionsException;

class LocationMatcherDirectLoad extends LocationMatcher
{
    /**
     * Override the parent's implementation to use the repository instead of the Search Service to load Locations when
     * specified by Id or RemoteId.
     * This has the advantage of not going through Solr, and hence having less problems with transactions and indexation delay.
     *
     * @param array $conditions
     * @param array $sort
     * @param int $offset
     * @param int $limit
     * @param bool $tolerateMisses
     * @return LocationCollection
     * @throws InvalidArgumentException
     * @throws InvalidMatchConditionsException
     * @throws InvalidSortConditionsException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function matchLocation(array $conditions, array $sort = array(), $offset = 0, $limit = 0, $tolerateMisses = false)
    {
        $match = reset($conditions);
        if (count($conditions) === 1 && in_array(($key = key($conditions)), array(self::MATCH_LOCATION_ID, self::MATCH_LOCATION_REMOTE_ID))) {
            $match = (array)$match;
            $locations = array();
            switch ($key) {
                case self::MATCH_LOCATION_ID:
                    foreach($match as $locationId) {
                        try {
                            $location = $this->repository->getLocationService()->loadLocation($locationId);
                            $locations[$location->id] = $location;
                        } catch(NotFoundException $e) {
                            if (!$tolerateMisses) {
                                throw $e;
                            }
                        }
                    }
                    break;
                case self::MATCH_LOCATION_REMOTE_ID:
                    foreach($match as $locationRemoteId) {
                        try {
                            $location = $this->repository->getLocationService()->loadLocationByRemoteId($locationRemoteId);
                            $locations[$location->id] = $location;
                        } catch(NotFoundException $e) {
                            if (!$tolerateMisses) {
                                throw $e;
                            }
                        }
                    }
                    break;
            }
            return new LocationCollection($locations);
        }

        return parent::matchLocation($conditions, $sort, $offset, $limit);
    }
}

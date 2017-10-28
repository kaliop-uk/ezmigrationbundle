<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use Kaliop\eZMigrationBundle\API\Collection\LocationCollection;

class LocationMatcherDirectLoad extends LocationMatcher
{
    /**
     * Override the parent's implementation to use the repository instead of the Search Service to load Locations when
     * specified by Id or RemoteId.
     * This has the advantage of not going through Solr, and hence having less problems with transactions and indexation delay.
     *
     * @param array $conditions
     * @return LocationCollection
     */
    public function matchLocation(array $conditions)
    {
        $match = reset($conditions);
        if (count($conditions) === 1 && in_array(($key = key($conditions)), array(self::MATCH_LOCATION_ID, self::MATCH_LOCATION_REMOTE_ID))) {
            $match = (array)$match;
            $locations = array();
            switch ($key) {
                case self::MATCH_LOCATION_ID:
                    foreach($match as $locationId) {
                        $locations[] = $this->repository->getLocationService()->loadLocation($locationId);
                    }
                    break;
                case self::MATCH_LOCATION_REMOTE_ID:
                    foreach($match as $locationRemoteId) {
                        $locations[] = $this->repository->getLocationService()->loadLocationByRemoteId($locationRemoteId);
                    }
                    break;
            }
            return new LocationCollection($locations);
        }

        return parent::matchLocation($conditions);
    }
}

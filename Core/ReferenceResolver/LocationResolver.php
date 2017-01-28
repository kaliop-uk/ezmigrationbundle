<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\Core\Matcher\LocationMatcher;

/**
 * Handles references to locations. At the moment: supports remote Ids.
 *
 * @todo drop support for 'location:' in favour of 'location_remote_id:'
 */
class LocationResolver extends AbstractResolver
{
    /**
     * Defines the prefix for all reference identifier strings in definitions
     */
    protected $referencePrefixes = array('location:' /*, 'location_remote_id:'*/);

    protected $locationMatcher;

    /**
     * @param LocationMatcher $locationMatcher
     */
    public function __construct(LocationMatcher $locationMatcher)
    {
        parent::__construct();

        $this->locationMatcher = $locationMatcher;
    }

    /**
     * @param $stringIdentifier location:<remote_id>
     * @return string Location id
     * @throws \Exception
     */
    public function getReferenceValue($stringIdentifier)
    {
        $ref = $this->getReferenceIdentifierByPrefix($stringIdentifier);
        switch ($ref['prefix']) {
            // deprecated tag: 'location:'
            case 'location:':
                return $this->locationMatcher->MatchOne(array(LocationMatcher::MATCH_LOCATION_REMOTE_ID => $ref['identifier']))->id;
            //case 'location_remote_id:':
            //    return $this->repository->getLocationService()->loadLocationByRemoteId($ref['identifier'])->id;
        }
    }
}

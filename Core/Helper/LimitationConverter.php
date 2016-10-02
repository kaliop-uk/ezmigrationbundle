<?php

namespace Kaliop\eZMigrationBundle\Core\Helper;

use eZ\Publish\API\Repository\Values\User\Limitation;
use Kaliop\eZMigrationBundle\Core\Matcher\LocationMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\SectionMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentTypeMatcher;

class LimitationConverter
{
    protected $locationMatcher;
    protected $sectionMatcher;
    protected $contentTypeMatcher;

    /** @var array  */
    protected $siteAccessList = array();

    public function __construct(
        array $siteAccessList,
        LocationMatcher $locationMatcher,
        SectionMatcher $sectionMatcher,
        ContentTypeMatcher $contentTypeMatcher
    )
    {
        $this->locationMatcher = $locationMatcher;
        $this->sectionMatcher = $sectionMatcher;
        $this->contentTypeMatcher = $contentTypeMatcher;

        foreach ($siteAccessList as $siteAccess) {
            $id = sprintf('%u', crc32($siteAccess));
            $this->siteAccessList[$id] = $siteAccess;
        }
    }

    /**
     * Used f.e. to generate a human-readable role definition: substitute entity ids with their identifiers/names
     *
     * @param Limitation $limitation
     * @return array keys: identifier, values
     */
    public function getLimitationArrayWithIdentifiers(Limitation $limitation)
    {
        $retValues = array();
        $values = $limitation->limitationValues;

        switch ($limitation->getIdentifier()) {
            case 'Section':
            case 'NewSection':
                foreach ($values as $value) {
                    $section = $this->sectionMatcher->matchByKey($value);
                    $retValues[] = $section->identifier;
                }
                break;
            case 'SiteAccess':
                foreach($values as $value) {
                    $name = "No Site Access Found";
                    if (array_key_exists($value, $this->siteAccessList)) {
                        $name = $this->siteAccessList[$value];
                    }
                    $retValues[] = $name;
                }
                break;
            case 'ParentClass':
            case 'Class':
                foreach($values as $value) {
                    $contentType = $this->contentTypeMatcher->matchByKey($value);
                    $retValues[] = $contentType->identifier;
                }
                break;
            default:
                $retValues = $values;
        }

        return array(
            'identifier' => $limitation->getIdentifier(),
            'values' => $retValues
        );
    }

    /**
     * Converts human readable limitation values to their numeric counterparts we get the array in 2 parts so the
     * referencing can be completed on it before it's sent to this method.
     *
     * @param $limitationIdentifier
     * @param array $values
     * @return array
     */
    public function resolveLimitationValue($limitationIdentifier, array $values)
    {
        $retValues = array();
        switch ($limitationIdentifier) {
            // q: is it worth doing this for nodes? does it make a translation at all?
            case 'Node':
                foreach ($values as $value) {
                    $location = $this->locationMatcher->matchByKey($value);
                    $retValues[] = $location->id;
                }
                break;
            case 'Section':
            case 'NewSection':
                foreach ($values as $value) {
                    $section = $this->sectionMatcher->matchByKey($value);
                    $retValues[] = $section->id;
                }
                break;
            case 'SiteAccess':
                foreach($values as $value) {
                    foreach ($this->siteAccessList as $key => $siteAccess) {
                        if ($value == $siteAccess) {
                            $retValues[] = (string)$key;
                        }
                    }
                }
                break;
            case 'ParentClass':
            case 'Class':
                foreach($values as $value) {
                    $contentType = $this->contentTypeMatcher->matchByKey($value);
                    $retValues[] = $contentType->id;
                }
                break;
            default:
                $retValues = $values;
        }

        return $retValues;
    }
}

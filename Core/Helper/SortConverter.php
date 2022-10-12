<?php

namespace Kaliop\eZMigrationBundle\Core\Helper;

use eZ\Publish\API\Repository\Values\Content\Location;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;

class SortConverter
{
    /**
     * @param string $value
     * @return int|null
     */
    public function hash2SortField($value)
    {
        $sortField = null;

        if ($value !== null) {
            // make life nicer for users - we never use 'contentobject' or 'node' in yml...
            if ($value == 'content_id') {
                $value = 'contentobject_id';
            }
            if ($value == 'location_id') {
                $value = 'node_id';
            }
            $sortFieldId = "SORT_FIELD_" . strtoupper($value);

            $ref = new \ReflectionClass('eZ\Publish\API\Repository\Values\Content\Location');

            $sortField = $ref->getConstant($sortFieldId);
        }

        return $sortField;
    }

    /**
     * @param int $value
     * @return string
     * @throws \Exception
     */
    public function sortField2Hash($value)
    {
        $ref = new \ReflectionClass('eZ\Publish\API\Repository\Values\Content\Location');
        foreach ($ref->getConstants() as $key => $val) {
            if (strpos($key, 'SORT_FIELD_') === 0 && $val == $value) {
                $out = strtolower(substr($key, 11));
                if ($out == 'contentobject_id') {
                    $out = 'content_id';
                }
                if ($out == 'node_id') {
                    $out = 'location_id';
                }
                return $out;
            }
        }

        throw new MigrationBundleException("Unknown sort field: '$value'");
    }

    /**
     * Get the sort order based on the current value and the value in the DSL definition.
     *
     * @see \eZ\Publish\API\Repository\Values\Content\Location::SORT_ORDER_*
     *
     * @param string $value ASC|DESC
     * @return int
     */
    public function hash2SortOrder($value)
    {
        $sortOrder = null;

        if ($value !== null) {
            if (strtoupper($value) === 'ASC') {
                $sortOrder = Location::SORT_ORDER_ASC;
            } else {
                $sortOrder = Location::SORT_ORDER_DESC;
            }
        }

        return $sortOrder;
    }

    /**
     * @param int $value
     * @return string
     */
    public function sortOrder2Hash($value)
    {
        if ($value === Location::SORT_ORDER_ASC) {
            return 'ASC';
        } else {
            return 'DESC';
        }
    }
}

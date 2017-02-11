<?php

namespace Kaliop\eZMigrationBundle\Core\Helper;

use eZ\Publish\API\Repository\Values\Content\Location;

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
        foreach($ref->getConstants() as $key => $val) {
            if (strpos($key, 'SORT_FIELD_') === 0 && $val == $value) {
                return strtolower(substr($key, 11));
            }
        }

        throw new \Exception("Unknown sort field: '$value'");
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

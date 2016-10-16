<?php

namespace Kaliop\eZMigrationBundle\API\Value;

use eZ\Publish\API\Repository\Exceptions\PropertyNotFoundException;

abstract class AbstractValue
{
    /**
     * Magic get function handling read to non public properties
     *
     * Returns value for all readonly (protected) properties.
     *
     * @ignore This method is for internal use
     * @access private
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\PropertyNotFoundException exception on all reads to undefined properties so typos are not silently accepted.
     *
     * @param string $property Name of the property
     *
     * @return mixed
     */
    public function __get($property)
    {
        if (property_exists($this, $property))
        {
            return $this->$property;
        }
        throw new PropertyNotFoundException($property, get_class($this));
    }

    /**
     * Magic isset function handling isset() to non public properties
     *
     * Returns true for all (public/)protected/private properties.
     *
     * @ignore This method is for internal use
     * @access private
     *
     * @param string $property Name of the property
     *
     * @return boolean
     */
    public function __isset($property)
    {
        return property_exists($this, $property);
    }
}
<?php

namespace Kaliop\eZMigrationBundle\API\Collection;

/**
 * Implements a 'typed array' structure
 */
class AbstractCollection extends \ArrayObject
{
    protected $allowedClass;

    /**
     * AbstractCollection constructor.
     * @param array $input
     * @param int $flags
     * @param string $iterator_class
     */
    public function __construct ($input = array(), $flags = 0, $iterator_class = "ArrayIterator")
    {
        foreach($input as $value) {
            if (!is_a($value, $this->allowedClass)) {
                $this->throwInvalid($value);
            }
        }
    }

    /**
     * @param mixed $value
     */
    public function append ($value)
    {
        if (!is_a($value, $this->allowedClass)) {
            $this->throwInvalid($value);
        }
    }

    /**
     * @param mixed $input
     */
    public function exchangeArray ($input)
    {
        foreach($input as $value) {
            if (!is_a($value, $this->allowedClass)) {
                $this->throwInvalid($value);
            }
        }
    }

    /**
     * @param mixed $index
     * @param mixed $newval
     */
    public function offsetSet ($index , $newval)
    {
        if (!is_a($newval, $this->allowedClass)) {
            $this->throwInvalid($newval);
        }
    }

    protected function throwInvalid($newval)
    {
        throw new \InvalidArgumentException("Can not add element of type '" . (is_object($newval) ? get_class($newval) : gettype($newval) ) . "' to Collection of type '" . get_class($this) . "'");
    }
}
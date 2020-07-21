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
    public function __construct($input = array(), $flags = 0, $iterator_class = "ArrayIterator")
    {
        foreach ($input as $value) {
            if (!$this->isValidElement($value)) {
                $this->throwInvalid($value);
            }
        }

        parent::__construct($input, $flags, $iterator_class);
    }

    /**
     * @param mixed $value
     */
    public function append($value)
    {
        if (!$this->isValidElement($value)) {
            $this->throwInvalid($value);
        }

        parent::append($value);
    }

    /**
     * @param mixed $input
     * @return array the old array
     */
    public function exchangeArray($input)
    {
        foreach ($input as $value) {
            if (!$this->isValidElement($value)) {
                $this->throwInvalid($value);
            }
        }

        return parent::exchangeArray($input);
    }

    /**
     * @param mixed $index
     * @param mixed $value
     */
    public function offsetSet($index, $value)
    {
        if (!$this->isValidElement($value)) {
            $this->throwInvalid($value);
        }

        parent::offsetSet($index, $value);
    }

    /**
     * Work around php 7.4 having removed support for calling `reset($this)`
     * @todo optimize this (esp. for big collections)
     */
    public function reset()
    {
        $results = $this->getArrayCopy();

        return reset($results);
    }

    protected function isValidElement($value)
    {
        return is_a($value, $this->allowedClass);
    }

    protected function throwInvalid($value)
    {
        throw new \InvalidArgumentException("Can not add element of type '" . (is_object($value) ? get_class($value) : gettype($value)) . "' to Collection of type '" . get_class($this) . "'");
    }

    /**
     * Allow the class to be serialized to php using var_export
     * @param array $data
     * @return static
     */
    public static function __set_state(array $data)
    {
        return new static($data);
    }
}

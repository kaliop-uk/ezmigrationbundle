<?php

namespace Kaliop\eZMigrationBundle\Core\API;

class Collection implements \ArrayAccess, \Iterator
{
    private $elements = [];

    private $position;

    public function __construct($elements)
    {
        $this->elements = $elements;
        $this->position = 0;
    }

    public function offsetSet($offset, $element)
    {
        if (is_null($offset)) {
            $this->elements[] = $element;
        } else {
            $this->elements[$offset] = $element;
        }
    }

    public function offsetExists($offset)
    {
        return isset($this->elements[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->elements[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->elements[$offset]) ? $this->elements[$offset] : null;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function current()
    {
        return $this->elements[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        ++$this->position;
    }

    public function valid()
    {
        return isset($this->elements[$this->position]);
    }


}
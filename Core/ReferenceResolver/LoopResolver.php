<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

class LoopResolver extends AbstractResolver
{
    protected $referencePrefixes = array('loop:');

    protected $stack = array();

    public function beginLoop()
    {
        $this->stack[] = 0;
    }

    public function endLoop()
    {
        array_pop($this->stack);
    }

    public function loopStep()
    {
        $idx = count($this->stack) - 1;
        $this->stack[$idx] = $this->stack[$idx] + 1;
    }

    /**
     * @param string $identifier format: 'loop:index', 'loop:depth'
     * @return int
     * @throws \Exception When trying to retrieve anything else but index and depth
     */
    public function getReferenceValue($identifier)
    {
        switch(substr($identifier, 5)) {
            case 'iteration':
                return end($this->stack);
            case 'depth':
                return count($this->stack);
            default:
                throw new \Exception("Can not resolve loop value '$identifier'");
        }
    }

}
<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\EnumerableReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;

class LoopResolver extends AbstractResolver implements EnumerableReferenceResolverInterface
{
    protected $referencePrefixes = array('loop:');

    protected $stack = array();

    public function beginLoop()
    {
        $this->stack[] = array('step' => 0, 'key' => null, 'value' => null);
    }

    public function endLoop()
    {
        array_pop($this->stack);
    }

    public function loopStep($key = null, $value = null)
    {
        $idx = count($this->stack) - 1;
        $this->stack[$idx]['step'] = $this->stack[$idx]['step'] + 1;
        $this->stack[$idx]['key'] = $key;
        $this->stack[$idx]['value'] = $value;
    }

    /**
     * @param string $identifier format: 'loop:iteration', 'loop:depth', 'loop:key', 'loop:value'
     * @return int
     * @throws \Exception When trying to retrieve anything else but index and depth
     */
    public function getReferenceValue($identifier)
    {
        switch (substr($identifier, 5)) {
            case 'iteration':
                $current = end($this->stack);
                return $current['step'];
            case 'key':
                $current = end($this->stack);
                return $current['key'];
            case 'value':
                $current = end($this->stack);
                return $current['value'];
            case 'depth':
                return count($this->stack);
            default:
                throw new MigrationBundleException("Can not resolve loop value '$identifier'");
        }
    }

    /**
     * We implement this method (interface) purely for convenience, as it allows this resolver to be added to the
     * 'custom-reference-resolver' chain and not break migration suspend/resume
     * @return array
     */
    public function listReferences()
    {
        return array();
    }
}

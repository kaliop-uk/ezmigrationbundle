<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

abstract class AbstractResolver implements ReferenceResolverInterface
{
    protected $referencePrefixes = array();
    protected $prefixMatchRegexp;

    /**
     * Always call this from constructor of subclasses!
     */
    public function __construct()
    {
        $quotedPrefixes = [];
        foreach($this->referencePrefixes as $prefix) {
            $quotedPrefixes[] = preg_quote($prefix, '/');
        }
        $this->prefixMatchRegexp = '/^(' . implode('|', $quotedPrefixes) . ')/';
    }

    /**
     * @param string $stringIdentifier
     * @return bool
     */
    public function isReference($stringIdentifier)
    {
        return (bool)preg_match($this->prefixMatchRegexp, $stringIdentifier);
    }

    /**
     * @param string $stringIdentifier
     * @return mixed
     */
    abstract public function getReferenceValue($stringIdentifier);

    /**
     * Returns the value of the reference identifier, stripped of its prefix.
     * Useful for subclasses with a single $referencePrefixes
     * @param string $stringIdentifier
     * @return string
     */
    protected function getReferenceIdentifier($stringIdentifier)
    {
        return preg_replace($this->prefixMatchRegexp, '', $stringIdentifier);
    }

    /**
     * Useful for subclasses with many $referencePrefixes
     * @param string $stringIdentifier
     * @return array with 2 keys, 'prefix' and 'identifier'
     * @throws \Exception
     */
    protected function getReferenceIdentifierByPrefix($stringIdentifier)
    {
        foreach($this->referencePrefixes as $prefix) {
            $regexp = '/^' . preg_quote($prefix, '/') . '/';
            if (preg_match($regexp, $stringIdentifier)) {
                return array('prefix' => $prefix, 'identifier' => preg_replace($regexp, '', $stringIdentifier));
            }
        }
        throw new \Exception("Can not match reference with identifier '$stringIdentifier'");
    }
}

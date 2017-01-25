<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

/**
 * A class which eases creating reference resolvers based on prefix strings.
 */
abstract class PrefixBasedResolver implements PrefixBasedResolverInterface
{
    /// To be set by subclasses at constructor time or in definition
    protected $referencePrefixes = array();

    private $prefixMatchRegexp;

    /**
     * NB: always call this from constructor of subclasses!
     */
    public function __construct()
    {
        $quotedPrefixes = [];
        foreach ($this->referencePrefixes as $prefix) {
            $quotedPrefixes[] = preg_quote($prefix, '/');
        }
        $this->prefixMatchRegexp = '/^(' . implode('|', $quotedPrefixes) . ')/';
    }

    public function getRegexp()
    {
        return $this->prefixMatchRegexp;
    }

    /**
     * @param string $stringIdentifier
     * @return bool
     */
    public function isReference($stringIdentifier)
    {
        if (!is_string($stringIdentifier)) {
            return false;
        }

        return (bool)preg_match($this->prefixMatchRegexp, $stringIdentifier);
    }

    public function resolveReference($stringIdentifier)
    {
        if ($this->isReference($stringIdentifier)) {
            return $this->getReferenceValue($stringIdentifier);
        }
        return $stringIdentifier;
    }

    /**
     * @param string $stringIdentifier
     * @return mixed
     */
    abstract public function getReferenceValue($stringIdentifier);

    /**
     * Returns the value-identifying part of the reference identifier, stripped of its prefix.
     * Useful for subclasses with a single $referencePrefixes
     *
     * @param string $stringIdentifier
     * @return string
     */
    protected function getReferenceIdentifier($stringIdentifier)
    {
        return preg_replace($this->prefixMatchRegexp, '', $stringIdentifier);
    }

    /**
     * Useful for subclasses with many $referencePrefixes
     *
     * @param string $stringIdentifier
     * @return array with 2 keys, 'prefix' and 'identifier'
     * @throws \Exception
     */
    protected function getReferenceIdentifierByPrefix($stringIdentifier)
    {
        foreach ($this->referencePrefixes as $prefix) {
            $regexp = '/^' . preg_quote($prefix, '/') . '/';
            if (preg_match($regexp, $stringIdentifier)) {
                return array('prefix' => $prefix, 'identifier' => preg_replace($regexp, '', $stringIdentifier));
            }
        }
        throw new \Exception("Can not match reference with identifier '$stringIdentifier'");
    }
}

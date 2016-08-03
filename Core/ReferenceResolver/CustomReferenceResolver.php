<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

/**
 * Handle 'any' references by letting the developer store them and retrieve them afterwards
 */
class CustomReferenceResolver extends AbstractResolver
{
    /**
     * Defines the prefix for all reference identifier strings in definitions
     */
    protected $referencePrefixes = array('reference:');

    /**
     * Array of all references set by the currently running migrations.
     *
     * @var array
     */
    private $references = array();

    /**
     * Get a stored reference
     *
     * @param string $identifier format: reference:<some_custom_identifier>
     * @return mixed
     * @throws \Exception When trying to retrieve an unset reference
     */
    public function getReferenceValue($identifier)
    {

        if (!array_key_exists($identifier, $this->references)) {
            throw new \Exception("No reference set with identifier '$identifier'");
        }

        return $this->references[$identifier];
    }

    /**
     * Add a reference to be retrieved later.
     *
     * @param string $identifier The identifier of the reference
     * @param mixed $value The value of the reference
     * @throws \Exception When there is a reference with the specified $identifier already.
     */
    public function addReference($identifier, $value)
    {
            if (array_key_exists($identifier, $this->references)) {
                throw new \Exception("A reference with identifier '$identifier' already exists");
            }

            $this->references[$identifier] = $value;
    }
}

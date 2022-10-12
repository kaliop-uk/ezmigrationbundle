<?php

namespace Kaliop\eZMigrationBundle\Core\ReferenceResolver;

use Kaliop\eZMigrationBundle\API\ReferenceResolverBagInterface;
use Kaliop\eZMigrationBundle\API\EnumerableReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\ContextProviderInterface;

/**
 * Handle 'any' references by letting the developer store them and retrieve them afterwards
 */
class CustomReferenceResolver extends AbstractResolver implements ReferenceResolverBagInterface,
    EnumerableReferenceResolverInterface, ContextProviderInterface
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
        $identifier = $this->getReferenceIdentifier($identifier);
        if (!array_key_exists($identifier, $this->references)) {
            throw new MigrationBundleException("No reference set with identifier '$identifier'");
        }

        return $this->references[$identifier];
    }

    /**
     * Add a reference to be retrieved later.
     *
     * @param string $identifier The identifier of the reference
     * @param mixed $value The value of the reference
     * @param bool $overwrite do overwrite the existing ref if it exist without raising an exception
     * @return bool true if the reference is accepted by this resolver, otherwise false
     * @throws \Exception When there is a reference with the specified $identifier already.
     */
    public function addReference($identifier, $value, $overwrite = false)
    {
        if (array_key_exists($identifier, $this->references) && !$overwrite) {
            throw new MigrationBundleException("A reference with identifier '$identifier' already exists");
        }

        $this->references[$identifier] = $value;

        return true;
    }

    /**
     * List all existing references
     * @return array
     */
    public function listReferences()
    {
        return $this->references;
    }

    /**
     * The custom reference resolver has only 'global' references, regardless of the current migration
     * @param string $migrationName
     * @return array|null
     */
    public function getCurrentContext($migrationName)
    {
        return $this->references;
    }

    /**
     * The custom reference resolver has only 'global' references, regardless of the current migration
     * @param string $migrationName
     * @param array $context
     */
    public function restoreContext($migrationName, array $context)
    {
        $this->references = $context;
    }
}

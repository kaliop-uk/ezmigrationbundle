<?php

namespace Kaliop\eZMigrationBundle\Core;

/**
 * Handle references.
 */
class ReferenceHandler
{
    /**
     * Constant defining the prefix for all reference identifier strings in definitions
     */
    const REFERENCE_PREFIX = 'reference:';

    /**
     * Array of all references set by the currently running migrations.
     *
     * @var array
     */
    private $references = array();

    /**
     * The instance of the ReferenceHandler
     * @var ReferenceHandler
     */
    private static $instance;

    /**
     * Private constructor as this is a singleton.
     */
    private function __construct()
    {

    }

    /**
     * Get the ReferenceHandler instance
     *
     * @return ReferenceHandler
     */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new ReferenceHandler();
        }

        return self::$instance;
    }

    /**
     * Get a stored reference
     *
     * @param string $identifier
     * @return mixed
     * @throws \Exception When trying to retrieve an unset reference
     */
    public function getReference($identifier)
    {

        if (!array_key_exists($identifier, $this->references)) {
            throw new \Exception('No reference set with identifier ' . $identifier);
        }

        return $this->references[$identifier];
    }

    /**
     * Add a reference to be retrieved later.
     *
     * @param string $identifier The identifier of the reference
     * @param mixed $value The value of the reference
     * @throws \Exception When there is a reference with the specified $scope and $identifier already.
     */
    public function addReference($identifier, $value)
    {

            if (array_key_exists($identifier, $this->references)) {
                throw new \Exception('A reference with identifier ' . $identifier . ' already exists.');
            }

            $this->references[$identifier] = $value;
    }
}

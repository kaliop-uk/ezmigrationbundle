<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Implemented by classes which finds migrations definitions files
 */
interface LoaderInterface
{
    /**
     * @param array $paths
     * @return string[] migrations definitions. key: name, value: contents of the definition as string
     */
    public function getDefinitions($paths = array());
}

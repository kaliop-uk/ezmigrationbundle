<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Implemented by classes which finds migrations definitions files
 */
interface LoaderInterface
{
    /**
     * @param array $paths either dir names or file names
     * @return string[] migrations definitions. key: name, value: path
     * @throws \Exception
     */
    public function listAvailableDefinitions($paths = array());

    /**
     * @param array $paths
     * @return string[] migrations definitions. key: name, value: contents of the definition, as string
     * @throws \Exception
     */
    public function loadDefinitions($paths = array());
}

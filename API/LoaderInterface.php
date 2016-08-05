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
    public function listAvailableDefinitions(array $paths = array());

    /**
     * @param array $paths
     * @return \Kaliop\eZMigrationBundle\API\Collection\MigrationDefinitionCollection unparsed definitions. key has to be the migration name
     * @throws \Exception
     */
    public function loadDefinitions(array $paths = array());
}

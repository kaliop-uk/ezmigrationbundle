<?php

namespace Kaliop\eZMigrationBundle\Core\Loader;

use Kaliop\eZMigrationBundle\API\LoaderInterface;

/**
 * Loads migratin definitions from disk
 */
class Filesystem implements LoaderInterface
{
    /**
     * Name of the directory where migration versions are located
     * @var string
     */
    protected $versionDirectory;

    public function __construct($versionDirectory = 'MigrationVersions')
    {
        $this->versionDirectory = $versionDirectory;
    }

    public function getDefinitions($paths = array())
    {
        /// @todo!!!
        return array();
    }
}
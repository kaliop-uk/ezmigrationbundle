<?php

namespace Kaliop\eZMigrationBundle\Core\Loader;

use Kaliop\eZMigrationBundle\API\LoaderInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Loads migration definitions from disk (by default from a specific dir in enabled bundles)
 */
class Filesystem implements LoaderInterface
{
    /**
     * Name of the directory where migration versions are located
     * @var string
     */
    protected $versionDirectory;
    protected $kernel;

    public function __construct(KernelInterface $kernel, $versionDirectory = 'MigrationVersions')
    {
        $this->versionDirectory = $versionDirectory;
        $this->kernel = $kernel;
    }

    /**
     * @param array $paths either dir names or file names
     * @return string[] migrations definitions. key: name, value: file path
     * @throws \Exception
     */
    public function listAvailableDefinitions($paths = array())
    {
        return $this->getDefinitions($paths, true);
    }

    /**
     * @param array $paths either dir names or file names
     * @return string[] migrations definitions. key: name, value: contents of the definition, as string
     * @throws \Exception
     */
    public function loadDefinitions($paths = array())
    {
        return $this->getDefinitions($paths, false);
    }

    /**
     * @param array $paths either dir names or file names
     * @param bool $returnFilename return either the
     * @return string[] migrations definitions. key: name, value: contents of the definition, as string or file path
     * @throws \Exception
     */
    protected function getDefinitions($paths = array(), $returnFilename = false)
    {
        // if no paths defined, we look in all bundles
        if (empty($paths)) {
            $paths = array();
            /** @var $bundle \Symfony\Component\HttpKernel\Bundle\BundleInterface */
            foreach( $this->kernel->getBundles() as $bundle )
            {
                $path = $bundle->getPath() . "/" . $this->versionDirectory;
                if (is_dir($path)) {
                    $paths[] = $path;
                }
            }
        }

        $definitions = array();
        foreach($paths as $path) {
            if (is_file($path)) {
                $definitions[basename($path)] = $returnFilename ? $path : file_get_contents($path);
            } elseif (is_dir($path)) {
                foreach (new \DirectoryIterator($path) as $file) {
                    if ($file->isFile()) {
                        $definitions[$file->getFilename()] = $returnFilename ? $file->getRealPath() : file_get_contents($file->getRealPath());
                    }
                }
            }
            else {
                throw new \Exception("Path '$path' is neither a file nor directory");
            }
        }
        return $definitions;
    }
}

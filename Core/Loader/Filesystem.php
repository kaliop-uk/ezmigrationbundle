<?php

namespace Kaliop\eZMigrationBundle\Core\Loader;

use Kaliop\eZMigrationBundle\API\LoaderInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Collection\MigrationDefinitionCollection;
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

    public function __construct(KernelInterface $kernel, $versionDirectory = 'Migrations')
    {
        $this->versionDirectory = $versionDirectory;
        $this->kernel = $kernel;
    }

    /**
     * @param array $paths either dir names or file names. If empty, will look in all registered bundles subdir
     * @return MigrationDefinition[] migrations definitions. key: name, value: file path
     * @throws \Exception
     */
    public function listAvailableDefinitions(array $paths = array())
    {
        return $this->getDefinitions($paths, true);
    }

    /**
     * @param array $paths either dir names or file names. If empty, will look in all registered bundles subdir
     * @return MigrationDefinitionCollection migrations definitions. key: name, value: contents of the definition, as string
     * @throws \Exception
     */
    public function loadDefinitions(array $paths = array())
    {
        return new MigrationDefinitionCollection($this->getDefinitions($paths, false));
    }

    /**
     * @param array $paths either dir names or file names
     * @param bool $returnFilename return either the
     * @return MigrationDefinition[]|string[] migrations definitions. key: name, value: contents of the definition, as string or file path
     * @throws \Exception
     */
    protected function getDefinitions(array $paths = array(), $returnFilename = false)
    {
        // if no paths defined, we look in all bundles
        if (empty($paths)) {
            $paths = array();
            /** @var $bundle \Symfony\Component\HttpKernel\Bundle\BundleInterface */
            foreach($this->kernel->getBundles() as $bundle)
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
                $definitions[basename($path)] = $returnFilename ? $path : new MigrationDefinition(
                    basename($path),
                    $path,
                    file_get_contents($path)
                );
            } elseif (is_dir($path)) {
                foreach (new \DirectoryIterator($path) as $file) {
                    if ($file->isFile()) {
                        $definitions[$file->getFilename()] =
                            $returnFilename ? $file->getRealPath() : new MigrationDefinition(
                                $file->getFilename(),
                                $file->getRealPath(),
                                file_get_contents($file->getRealPath())
                            );
                    }
                }
            }
            else {
                throw new \Exception("Path '$path' is neither a file nor directory");
            }
        }
        ksort($definitions);

        return $definitions;
    }
}

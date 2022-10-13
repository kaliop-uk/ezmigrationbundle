<?php

namespace Kaliop\eZMigrationBundle\Core\Loader;

use Kaliop\eZMigrationBundle\API\LoaderInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Collection\MigrationDefinitionCollection;
use Kaliop\eZMigrationBundle\API\ConfigResolverInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
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

    /**
     * Filesystem constructor.
     * @param KernelInterface $kernel
     * @param string $versionDirectoryParameter name of folder when $configResolver is null; name of parameter when it is not
     * @param ConfigResolverInterface $configResolver
     * @throws \Exception
     */
    public function __construct(KernelInterface $kernel, $versionDirectoryParameter = 'Migrations', ConfigResolverInterface $configResolver = null)
    {
        $this->kernel = $kernel;
        $this->versionDirectory = $configResolver ? $configResolver->getParameter($versionDirectoryParameter) : $versionDirectoryParameter;
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
        // if no paths defined, we look in all available paths
        if (empty($paths)) {
            $paths = array();
            // Add the bundle paths
            /** @var $bundle \Symfony\Component\HttpKernel\Bundle\BundleInterface */
            foreach ($this->kernel->getBundles() as $bundle)
            {
                $path = $bundle->getPath() . "/" . $this->versionDirectory;
                if (is_dir($path)) {
                    $paths[] = $path;
                }
            }
            // Also look for migrations in app directory
            $app_dir = $this->kernel->getRootDir();
            $app_dir = $app_dir . "/" . $this->versionDirectory;
            if (is_dir($app_dir)) {
                $paths[] = $app_dir;
            }
        }

        $rootDir = realpath($this->kernel->getRootDir() . '/..') . '/';

        $definitions = array();
        foreach ($paths as $path) {
            // we normalize all paths and try to make them relative to the root dir, both in input and output
            if ($path === './') {
                $path = $rootDir;
            } elseif ($path[0] !== '/') {
                $path = $rootDir . $path;
            }

            if (is_file($path)) {
                $path = realpath($path);
                $definitions[basename($path)] = $returnFilename ? $this->normalizePath($path) : new MigrationDefinition(
                    basename($path),
                    $this->normalizePath($path),
                    file_get_contents($path)
                );
            } elseif (is_dir($path)) {
                foreach (new \DirectoryIterator($path) as $file) {
                    if ($file->isFile()) {
                        $definitions[$file->getFilename()] =
                            $returnFilename ? $this->normalizePath($file->getRealPath()) : new MigrationDefinition(
                                $file->getFilename(),
                                $this->normalizePath($file->getRealPath()),
                                file_get_contents($file->getRealPath())
                            );
                    }
                }
            } else {
                throw new MigrationBundleException("Path '$path' is neither a file nor directory");
            }
        }
        ksort($definitions);

        return $definitions;
    }

    /**
     * @param string $path should be an absolute path
     * @return string the same path, but relative to current root dir if it is a subpath
     */
    protected function normalizePath($path)
    {
        $rootDir = realpath($this->kernel->getRootDir() . '/..') . '/';
        // note: we handle the case of 'path = root dir', but path is expected to include a filename...
        return $path === $rootDir ? './' : preg_replace('#^' . preg_quote($rootDir, '#'). '#', '', $path);
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Loader;

use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;

/**
 * Similar to parent, except that, given a folder, we scan all of its subfolders
 *
 * @todo allow to specify specific subfolders not to scan, eg: where the media files are stored
 */
class FilesystemRecursive extends Filesystem
{
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

                // seems like sorting of recursive dirs scan is though...

                $dirs = array();
                $DI = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO);
                foreach (new \RecursiveIteratorIterator($DI) as $file) {
                    if ($file->isFile()) {
                        $dirs[dirname($file->getRealpath())] = true;
                    }
                }
                $dirs = array_keys($dirs);
                asort($dirs);

                foreach ($dirs as $dir) {
                    foreach (glob("$dir/*") as $filename) {
                        if (is_file($filename)) {
                            $definitions[basename($filename)] =
                                $returnFilename ? realpath($filename) : new MigrationDefinition(
                                    basename($filename),
                                    realpath($filename),
                                    file_get_contents($filename)
                                );
                        }
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

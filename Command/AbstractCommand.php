<?php

namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Kaliop\eZMigrationBundle\Core\Configuration;

/**
 * Base command class that all migration commands extend from.
 */
abstract class AbstractCommand extends ContainerAwareCommand
{
    private $migrationService;

    public function getMigrationService()
    {
        if (!$this->migrationService)
        {
            $this->migrationService = $this->getContainer()->get('ez_migration_bundle.migration_service');
        }

        return $this->migrationService;
    }



    /**
     * Get the paths to all registered bundles.
     *
     * The method appends the migration version directory at the end of each path.
     *
     * @return array
     */
    public function getBundlePaths()
    {
        $paths = array();

        $container = $this->getApplication()->getKernel()->getContainer();

        $versionDirectory = $container->getParameter( 'ez_migration_bundle.version_directory' );

        /** @var $bundle \Symfony\Component\HttpKernel\Bundle\BundleInterface */
        foreach( $this->getApplication()->getKernel()->getBundles() as $bundle )
        {
            // @TODO: fix directory name to come from a config file
            $path = $bundle->getPath() . "/{$versionDirectory}";
            $name = $bundle->getName();

            $paths[$name] = $path;
        }

        return $paths;
    }

    /**
     * Group the paths by bundle
     *
     * @param array $paths
     * @return array
     */
    public function groupPathsByBundle( $paths )
    {
        $groupedPaths = array();
        $bundles = $this->getBundlePaths();

        foreach( $paths as $path )
        {
            if( file_exists( $path ) && !is_dir( $path ) )
            {
                $path = dirname( $path );
            }

            if( ($bundle = array_search( $path, $bundles ) ) )
            {
               $groupedPaths[$bundle][] = $path;
            }
        }

        return $groupedPaths;
    }
}

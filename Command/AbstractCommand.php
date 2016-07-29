<?php

namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\DBAL\DriverManager;
use Kaliop\eZMigrationBundle\Core\Configuration;

/**
 * Base command class that all migration commands extend from.
 */
abstract class AbstractCommand extends Command
{
    /**
     * The migration configuration object.
     *
     * @var \Kaliop\eZMigrationBundle\Core\Configuration
     */
    private $configuration;

    protected function configure()
    {

    }

    /**
     * Set the migration configuration
     *
     * @param Configuration $configuration
     * @return Configuration
     */
    public function setConfiguration( Configuration $configuration )
    {
        return ( $this->configuration = $configuration );
    }

    /**
     * Return the migration configuration
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return Configuration
     */
    public function getConfiguration( InputInterface $input, OutputInterface $output )
    {
        if( !$this->configuration )
        {
            $container = $this->getApplication()->getKernel()->getContainer();

            $conn = $container->get( 'ezpublish.connection' );

            $configuration = new Configuration( $conn, $output );
            $configuration->versionDirectory = $container->getParameter( 'kaliop_bundle_migration.version_directory' );
            $configuration->versionTableName = $container->getParameter( 'kaliop_bundle_migration.table_name' );
            $this->configuration = $configuration;

        }

        return $this->configuration;
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

        $versionDirectory = $container->getParameter( 'kaliop_bundle_migration.version_directory' );

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

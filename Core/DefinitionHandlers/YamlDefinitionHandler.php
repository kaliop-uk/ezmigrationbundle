<?php
namespace Kaliop\Migration\BundleMigrationBundle\Core\DefinitionHandlers;

use Kaliop\Migration\BundleMigrationBundle\Core\API\Managers\ContentManager;
use Kaliop\Migration\BundleMigrationBundle\Core\API\Managers\ContentTypeManager;
use Kaliop\Migration\BundleMigrationBundle\Core\API\Managers\LocationManager;
use Kaliop\Migration\BundleMigrationBundle\Core\API\Managers\RoleManager;
use Kaliop\Migration\BundleMigrationBundle\Core\API\Managers\UserGroupManager;
use Kaliop\Migration\BundleMigrationBundle\Core\API\Managers\UserManager;
use Kaliop\Migration\BundleMigrationBundle\Interfaces\BundleAwareInterface;
use Kaliop\Migration\BundleMigrationBundle\Interfaces\VersionInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class YamlDefinitionHandler
 *
 * This class handles Yaml migration definitions.
 *
 * @package Kaliop\Migration\BundleMigrationBundle\Core\DefinitionHandlers
 */
class YamlDefinitionHandler implements VersionInterface, ContainerAwareInterface, BundleAwareInterface
{

    /**
     * File path to the Yaml definition file
     * @var string
     */
    public $yamlFile;

    /**
     * The service container object from Symfony
     * @var ContainerInterface
     */
    private $container;

    /**
     * The bundle the migration version is for.
     *
     * @var \Symfony\Component\HttpKernel\Bundle\BundleInterface
     */
    private $bundle;

    /**
     * Execute the migration based on the instructions in the Yaml definition file
     *
     * @throws \Exception when an undefined migration type is found.
     */
    public function execute()
    {
        // Parse the Yaml instructions file
        $dsl = Yaml::parse($this->yamlFile);

        foreach ($dsl as $instructions) {

            // Check if the instruction has a mode and type or bail out as we cannot continue
            if (!array_key_exists('mode', $instructions)) {
                throw new \Exception('Missing migration mode');
            }

            if (!array_key_exists('type', $instructions)) {
                throw new \Exception('Missing migration type');
            }

            // Handle the instruction type
            switch ($instructions['type']) {
                case 'content':
                    $manager = new ContentManager();

                    // Pass the Service Container to the manager
                    $manager->setContainer($this->container);

                    // Pass the bundle to the manager
                    $manager->setBundle($this->bundle);

                    // Add the migration instructions
                    $manager->setDSL($instructions);
                    $manager->handle();
                    break;

                case 'content_type':
                    $manager = new ContentTypeManager();
                    $manager->setContainer($this->container);
                    $manager->setDSL($instructions);
                    $manager->handle();
                    break;
                case 'user':
                    $manager = new UserManager();
                    $manager->setContainer($this->container);
                    $manager->setDSL($instructions);
                    $manager->handle();
                    break;
                case 'user_group':
                    $manager = new UserGroupManager();
                    $manager->setContainer($this->container);
                    $manager->setDSL($instructions);
                    $manager->handle();
                    break;
                case 'role':
                    $manager = new RoleManager();
                    $manager->setContainer($this->container);
                    $manager->setDSL($instructions);
                    $manager->handle();
                    break;
                case 'location':
                    $manager = new LocationManager();
                    $manager->setContainer($this->container);
                    $manager->setDSL($instructions);
                    $manager->handle();
                    break;
                case 'policy':
                default:
                    throw new \Exception('Unknown migration type');
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @inheritdoc
     */
    public function setBundle(BundleInterface $bundle = null)
    {
        $this->bundle = $bundle;
    }
}
<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionHandler;

use Kaliop\eZMigrationBundle\Core\Executor\ContentManager;
use Kaliop\eZMigrationBundle\Core\Executor\ContentTypeManager;
use Kaliop\eZMigrationBundle\Core\Executor\LocationManager;
use Kaliop\eZMigrationBundle\Core\Executor\RoleManager;
use Kaliop\eZMigrationBundle\Core\Executor\TagManager;
use Kaliop\eZMigrationBundle\Core\Executor\UserGroupManager;
use Kaliop\eZMigrationBundle\Core\Executor\UserManager;
use Kaliop\eZMigrationBundle\API\BundleAwareInterface;
use Kaliop\eZMigrationBundle\API\DefinitionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Handles Yaml migration definitions.
 */
class YamlDefinitionHandler implements DefinitionHandlerInterface, ContainerAwareInterface, BundleAwareInterface
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
     * Tells whether the given file can be handled by this handler, by checking e.g. the suffix
     *
     * @param string $migrationName typically a filename
     * @return bool
     */
    public function supports($migrationName)
    {
        $ext = pathinfo($migrationName, PATHINFO_EXTENSION);
        return  $ext == 'yml' || $ext == 'yaml';
    }

    /**
     * Analyze a migration file to determine whether it is valid or not.
     * This will be only called on files that pass the supports() call
     *
     * @param string $migrationName typically a filename
     * @param string $contents
     * @throws \Exception if the file is not valid for any reason
     */
    public function isValidMigrationDefinition($migrationName, $contents)
    {
        $data = Yaml::parse($fileName);
        foreach($data as $stepDef) {
            if (!isset($stepDef['type'])) {
                throw new \Exception("Missing 'type' for migration definition step in file '$migrationName'");
            }
        }
    }

    /**
     * Parses a migration definition file, and returns the list of actions to take
     *
     * @param string $migrationName typically a filename
     * @param string $contents
     * @return \Kaliop\eZMigrationBundle\API\Value\MigrationDefinition
     */
    public function parseMigrationDefinition($migrationName, $contents)
    {
        $data = Yaml::parse($contents);

        $stepDefs = array();
        foreach($data as $stepDef) {
            $type = $stepDef['type'];
            unset($stepDef['type']);
            $stepDefs[] = new MigrationStep($type, $stepDef);
        }

        return new MigrationDefinition($migrationName, $stepDefs);
    }



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
                case 'tag':
                    $manager = new TagManager();
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

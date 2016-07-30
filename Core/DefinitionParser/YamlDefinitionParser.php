<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionParser;

use Kaliop\eZMigrationBundle\Core\Executor\ContentManager;
use Kaliop\eZMigrationBundle\Core\Executor\ContentTypeManager;
use Kaliop\eZMigrationBundle\Core\Executor\LocationManager;
use Kaliop\eZMigrationBundle\Core\Executor\RoleManager;
use Kaliop\eZMigrationBundle\Core\Executor\TagManager;
use Kaliop\eZMigrationBundle\Core\Executor\UserGroupManager;
use Kaliop\eZMigrationBundle\Core\Executor\UserManager;
use Kaliop\eZMigrationBundle\API\BundleAwareInterface;
use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Handles Yaml migration definitions.
 */
class YamlDefinitionParser implements DefinitionParserInterface, ContainerAwareInterface, BundleAwareInterface
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
     * Parses a migration definition file, and returns the list of actions to take
     *
     * @param MigrationDefinition $definition
     * @return MigrationDefinition
     */
    public function parseMigrationDefinition(MigrationDefinition $definition)
    {
        try {
            $data = Yaml::parse($definition->rawDefinition);
        } catch(\Exception $e) {
            return new MigrationDefinition(
                $definition->name,
                $definition->path,
                $definition->rawDefinition,
                MigrationDefinition::STATUS_INVALID,
                array(),
                $e->getMessage()
            );
        }

        $stepDefs = array();
        foreach($data as $stepDef) {
            $type = $stepDef['type'];
            unset($stepDef['type']);
            $stepDefs[] = new MigrationStep($type, $stepDef);
        }

        return new MigrationDefinition(
            $definition->name,
            $definition->path,
            $definition->rawDefinition,
            MigrationDefinition::STATUS_PARSED,
            $stepDefs
        );
    }

/// *** BELOW THE FOLD: TO BE REFACTORED ***

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

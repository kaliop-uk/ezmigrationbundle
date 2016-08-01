<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionParser;

use Kaliop\eZMigrationBundle\API\BundleAwareInterface;
use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Handles Yaml migration definitions.
 */
class YamlDefinitionParser implements DefinitionParserInterface, BundleAwareInterface
{
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
     * @inheritdoc
     */
    public function setBundle(BundleInterface $bundle = null)
    {
        $this->bundle = $bundle;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionParser;

use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Symfony\Component\Yaml\Yaml;

/**
 * Handles Yaml migration definitions.
 */
class YamlDefinitionParser implements DefinitionParserInterface
{
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

        // basic validation

        /// @todo move to using the Validator component...

        $status = MigrationDefinition::STATUS_PARSED;

        if (!is_array($data)) {
            $status = MigrationDefinition::STATUS_INVALID;
            $message = "Yaml migration file '{$definition->path}' must contain an array as top element";
        } else {
            foreach($data as $i => $stepDef) {
                if (!isset($stepDef['type']) || !is_string($stepDef['type'])) {
                    $status = MigrationDefinition::STATUS_INVALID;
                    $message = "Yaml migration file '{$definition->path}' misses or has a non-string 'type' element in step $i";
                    break;
                }
            }
        }

        if ($status != MigrationDefinition::STATUS_PARSED)
        {
            return new MigrationDefinition(
                $definition->name,
                $definition->path,
                $definition->rawDefinition,
                $status,
                array(),
                $message
            );
        }

        $stepDefs = array();
        foreach($data as $stepDef) {
            $type = $stepDef['type'];
            unset($stepDef['type']);
            $stepDefs[] = new MigrationStep($type, $stepDef, array('path' => $definition->path));
        }

        return new MigrationDefinition(
            $definition->name,
            $definition->path,
            $definition->rawDefinition,
            MigrationDefinition::STATUS_PARSED,
            $stepDefs
        );
    }
}

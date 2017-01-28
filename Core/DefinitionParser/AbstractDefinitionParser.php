<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionParser;

use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

class AbstractDefinitionParser
{
    /**
     * Parses a migration definition in the form of an array of steps
     *
     * @param array $data
     * @param MigrationDefinition $definition
     * @param string $format
     * @return MigrationDefinition
     */
    protected function parseMigrationDefinitionData($data, MigrationDefinition $definition, $format = 'Yaml')
    {
        // basic validation

        /// @todo move to using the Validator component...

        $status = MigrationDefinition::STATUS_PARSED;

        if (!is_array($data)) {
            $status = MigrationDefinition::STATUS_INVALID;
            $message = "$format migration file '{$definition->path}' must contain an array as top element";
        } else {
            foreach ($data as $i => $stepDef) {
                if (!isset($stepDef['type']) || !is_string($stepDef['type'])) {
                    $status = MigrationDefinition::STATUS_INVALID;
                    $message = "$format migration file '{$definition->path}' misses or has a non-string 'type' element in step $i";
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
        foreach ($data as $stepDef) {
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

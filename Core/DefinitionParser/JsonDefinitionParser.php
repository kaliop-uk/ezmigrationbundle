<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionParser;

use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;

/**
 * Handles Json migration definitions.
 */
class JsonDefinitionParser extends AbstractDefinitionParser implements DefinitionParserInterface
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
        return  $ext == 'json';
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
            // php 7.3 and later
            if (defined('JSON_THROW_ON_ERROR')) {
                $data = json_decode($definition->rawDefinition, true, 512, JSON_THROW_ON_ERROR);
            } else {
                $data = json_decode($definition->rawDefinition, true);
                if (json_last_error() != JSON_ERROR_NONE) {
                    throw new \Exception(json_last_error_msg());
                }
            }
        } catch (\Exception $e) {
            return new MigrationDefinition(
                $definition->name,
                $definition->path,
                $definition->rawDefinition,
                MigrationDefinition::STATUS_INVALID,
                array(),
                $e->getMessage()
            );
        }

        return $this->parseMigrationDefinitionData($data, $definition, 'Json');
    }
}

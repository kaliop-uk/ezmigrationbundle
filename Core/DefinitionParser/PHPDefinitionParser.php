<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionParser;

use Kaliop\eZMigrationBundle\API\DefinitionParserInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use PhpParser\Error;
use PhpParser\ParserFactory;

class PHPDefinitionParser implements DefinitionParserInterface
{
    protected $mandatoryInterface = 'Kaliop\eZMigrationBundle\API\MigrationInterface';

    /**
     * Tells whether the given file can be handled by this handler, by checking e.g. the suffix
     *
     * @param string $migrationName typically a filename
     * @return bool
     */
    public function supports($migrationName)
    {
        return pathinfo($migrationName, PATHINFO_EXTENSION) == 'php';
    }

    /**
     * Parses a migration definition file, and returns the list of actions to take
     *
     * @param MigrationDefinition $definition
     * @return MigrationDefinition
     */
    public function parseMigrationDefinition(MigrationDefinition $definition)
    {
        $status = MigrationDefinition::STATUS_PARSED;

        /// validate that php file is ok, contains a class with good interface
        $className = $this->getClassNameFromFile($definition->path);

        if ($className == '') {
            $status = MigrationDefinition::STATUS_INVALID;
            $message = 'The migration definition file should contain a valid class name. The class name is the part of the filename after the 1st underscore';
        } else {

            // we use smart parsing instead before plain file inclusion, by usage of nikic/php-parser
            // this should help with broken php migrations

            $pf = new ParserFactory();
            $parser = $pf->create(ParserFactory::PREFER_PHP7);
            try {
                $parser->parse(file_get_contents($definition->path));

                include_once($definition->path);

                if (!class_exists($className)) {
                    $status = MigrationDefinition::STATUS_INVALID;
                    $message = "The migration definition file should contain a valid class '$className'";
                } else {
                    $interfaces = class_implements($className);
                    if (!in_array($this->mandatoryInterface, $interfaces)) {
                        $status = MigrationDefinition::STATUS_INVALID;
                        $message = "The migration definition class '$className' should implement the interface '{$this->mandatoryInterface}'";
                    }
                }
            } catch (Error $e) {
                $status = MigrationDefinition::STATUS_INVALID;
                $message = "The migration definition file '{$definition->path}' is not valid php'";
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

        return new MigrationDefinition(
            $definition->name,
            $definition->path,
            $definition->rawDefinition,
            MigrationDefinition::STATUS_PARSED,
            array(
                new MigrationStep('php', array('class' => $className), array('path' => $definition->path))
            )
        );
    }

    protected function getClassNameFromFile($fileName)
    {
        $parts = explode('_', pathinfo($fileName, PATHINFO_FILENAME), 2);
        return isset($parts[1]) ? $parts[1] : null;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\DefinitionHandler;

use Kaliop\eZMigrationBundle\API\DefinitionHandlerInterface;

class PHPDefinitionHandler implements DefinitionHandlerInterface
{
    /**
     * Tells whether the given file can be handled by this handler, by checking e.g. the suffix
     *
     * @param string $fileName full path to filename
     * @return bool
     */
    public function supports($fileName)
    {
         /// @todo
    }

    /**
     * Analyze a migration file to determine whether it is valid or not.
     * This will be only called on files that pass the supports() call
     *
     * @param string $fileName full path to filename
     * @throws \Exception if the file is not valid for any reason
     */
    public function isValidMigration($fileName)
    {
        /// @todo
    }

    /**
     * Parses a migration definition file, and returns the list of actions to take
     *
     * @param string $fileName full path to filename
     * @return array key: the action to take, value: the action-specific definition (an array)
     */
    public function parseMigration($fileName)
    {
        /// @todo
    }
}

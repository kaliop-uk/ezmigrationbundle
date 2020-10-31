<?php

namespace Kaliop\eZMigrationBundle\API\Exception;

/**
 * To be used when a migration step definition is intrinsically invalid (as opposed to step definitions that are
 * invalid because of runtime conditions)
 */
class InvalidStepDefinitionException extends \Exception
{
}

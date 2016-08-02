<?php

namespace Kaliop\eZMigrationBundle\API\Collection;

/**
 * @todo add phpdoc to suggest typehinting
 */
class MigrationStepsCollection extends \ArrayObject
{
    protected $allowedClass = 'Kaliop\eZMigrationBundle\API\Value\MigrationStep';
}

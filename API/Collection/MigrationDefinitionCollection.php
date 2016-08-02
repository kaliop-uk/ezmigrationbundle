<?php

namespace Kaliop\eZMigrationBundle\API\Collection;

/**
 * @todo add phpdoc to suggest typehinting
 * @todo sort by key upon creation
 */
class MigrationDefinitionCollection extends \ArrayObject
{
    protected $allowedClass = 'Kaliop\eZMigrationBundle\API\Value\MigrationDefinition';
}

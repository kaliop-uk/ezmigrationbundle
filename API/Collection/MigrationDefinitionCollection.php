<?php

namespace Kaliop\eZMigrationBundle\API\Collection;

/**
 * @todo add phpdoc to suggest typehinting
 * @todo sort by key upon creation
 */
class MigrationDefinitionCollection extends AbstractCollection
{
    protected $allowedClass = 'Kaliop\eZMigrationBundle\API\Value\MigrationDefinition';
}

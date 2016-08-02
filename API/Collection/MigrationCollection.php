<?php

namespace Kaliop\eZMigrationBundle\API\Collection;

/**
 * @todo add phpdoc to suggest typehinting
 */
class MigrationCollection extends \ArrayObject
{
    protected $allowedClass = 'Kaliop\eZMigrationBundle\API\Value\Migration';
}

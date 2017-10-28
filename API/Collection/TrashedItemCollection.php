<?php

namespace Kaliop\eZMigrationBundle\API\Collection;

/**
 * @todo add phpdoc to suggest typehinting
 */
class TrashedItemCollection extends AbstractCollection
{
    protected $allowedClass = 'eZ\Publish\API\Repository\Values\Content\TrashItem';
}

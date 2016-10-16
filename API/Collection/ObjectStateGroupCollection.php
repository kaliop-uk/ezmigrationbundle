<?php

namespace Kaliop\eZMigrationBundle\API\Collection;

use eZ\Publish\API\Repository\Values\ObjectState\ObjectStateGroup;

/**
 * @todo add phpdoc to suggest typehinting
 */
class ObjectStateGroupCollection extends AbstractCollection
{
    protected $allowedClass = ObjectStateGroup::class;
}

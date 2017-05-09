<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

abstract class AbstractFieldHandler
{
    /** @var ReferenceResolverInterface $referenceResolver */
    protected $referenceResolver;

    public function setReferenceResolver(ReferenceResolverInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
    }
}

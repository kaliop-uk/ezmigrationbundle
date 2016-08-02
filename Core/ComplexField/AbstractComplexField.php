<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

abstract class AbstractComplexField
{
    /** @var ReferenceResolverInterface $referenceResolver */
    protected $referenceResolver;

    public function setReferenceResolver(ReferenceResolverInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
    }
}

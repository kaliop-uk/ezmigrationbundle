<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;

abstract class AbstractComplexField implements ComplexFieldInterface
{
    /** @var ReferenceResolverInterface $referenceResolver */
    protected $referenceResolver;

    public function setReferenceResolver(ReferenceResolverInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
    }

    /// BC
    public function createValue($fieldValue, array $context = array())
    {
        return $this->hashToFieldValue($fieldValue, $context);
    }
}

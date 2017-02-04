<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\FieldSettingsHandlerInterface;

abstract class AbstractComplexField implements FieldSettingsHandlerInterface
{
    /** @var ReferenceResolverInterface $referenceResolver */
    protected $referenceResolver;

    public function setReferenceResolver(ReferenceResolverInterface $referenceResolver)
    {
        $this->referenceResolver = $referenceResolver;
    }

    /**
     * Does no translation by default. Override in subclasses
     */
    public function fieldSettingsToHash($settingsValue, array $context = array())
    {
        return $settingsValue;
    }

    /**
     * Does no translation by default. Override in subclasses
     */
    public function hashToFieldSettings($settingsHash, array $context = array())
    {
        return $settingsHash;
    }
}

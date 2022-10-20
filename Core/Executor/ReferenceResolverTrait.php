<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\AbstractCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchResultsNumberException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

/**
 * A trait, used by Executors, which has code useful to resolve references
 */
trait ReferenceResolverTrait
{
    /** @var ReferenceResolverInterface */
    protected $referenceResolver;

    protected function resolveReference($identifier)
    {
        return $this->referenceResolver->resolveReference($identifier);
    }

    /**
     * @todo should be moved into the reference resolver classes
     */
    protected function resolveReferencesRecursively($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $values) {
                $value[$key] = $this->resolveReferencesRecursively($values);
            }
            return $value;
        } else {
            return $this->resolveReference($value);
        }
    }
}

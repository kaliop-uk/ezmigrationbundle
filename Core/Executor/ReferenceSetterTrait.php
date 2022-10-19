<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\AbstractCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchResultsNumberException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

/**
 * A trait used by Executors which hsa code useful to set values to references.
 */
trait ReferenceSetterTrait
{
    /**
     * Allows to have refs defined using two syntax variants:
     *   - { identifier: xxx, attribute: yyy, overwrite: bool }
     * or
     *   identifier: attribute
     * @param $key
     * @param $value
     * @return array
     * @throws InvalidStepDefinitionException
     *
     * @todo should we resolve references in ref identifiers, attributes and overwrite? Inception! :-D
     */
    protected function parseReferenceDefinition($key, $value)
    {
        if (is_string($key) && is_string($value)) {
            return array('identifier' => $key, 'attribute' => $value);
        }
        if (!is_array($value) || !isset($value['identifier']) || ! isset($value['attribute'])) {
            throw new InvalidStepDefinitionException("Invalid reference definition for reference number $key");
        }
        return $value;
    }
}

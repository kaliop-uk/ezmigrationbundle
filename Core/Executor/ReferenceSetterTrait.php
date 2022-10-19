<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\AbstractCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchResultsNumberException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

/**
 * A trait used by Executors which hsa code useful to set values to references.
 * @todo add a method that validates the 'references' key. Atm all we can check is that it is an array (we could as well
 * reject empty reference arrays in fact)
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

    /**
     * Valid reference values are either scalars or nested arrays. No objects, no resources.
     * @param mixed $value
     * @return bool
     */
    protected function isValidReferenceValue($value)
    {
        if (is_scalar($value)) {
            return true;
        }
        if (is_object($value) || is_resource($value)) {
            return false;
        }
        foreach($value as $subValue) {
            if (!$this->isValidReferenceValue($subValue)) {
                return false;
            }
        }
        return true;
    }
}

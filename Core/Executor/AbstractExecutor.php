<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\ExecutorInterface;

abstract class AbstractExecutor implements ExecutorInterface
{
    protected $supportedStepTypes = array();

    public function supportedTypes()
    {
        return $this->supportedStepTypes;
    }

    /**
     * IT IS MANDATORY TO OVERRIDE THIS IN SUBCLASSES
     * @param MigrationStep $step
     * @return mixed
     * @throws InvalidStepDefinitionException
     */
    public function execute(MigrationStep $step)
    {
        if (!in_array($step->type, $this->supportedStepTypes)) {
            throw new InvalidStepDefinitionException("Something is wrong! Executor can not work on step of type '{$step->type}'");
        }
    }

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
     * @todo move to a referenceSetter trait ?
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

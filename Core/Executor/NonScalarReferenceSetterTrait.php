<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\AbstractCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchResultsNumberException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

/**
 * A trait used by Executors which allow to set non-scalar values to references.
 * ATM besides scalars we support arrays and collections as reference values
 */
trait NonScalarReferenceSetterTrait
{
    // traits can not have constants...

    static $RESULT_TYPE_SINGLE = 1;
    static $RESULT_TYPE_MULTIPLE = 2;
    static $RESULT_TYPE_ANY = 3;

    static $EXPECT_NONE = 'none';
    static $EXPECT_ONE = 'one';
    static $EXPECT_ANY = 'any';
    static $EXPECT_MANY = 'many';
    static $EXPECT_UNSPECIFIED = 'unspecified';

    /**
     * @param mixed $results
     * @param MigrationStep $step
     * @throws InvalidMatchResultsNumberException
     * @throws InvalidStepDefinitionException
     */
    protected function validateResultsCount($results, $step)
    {
        // q: what if we get a scalar result but we expect an array/collection ?
        // q2: why not just check for Countable interface instead of AbstractCollection? Or at least allow ArrayIterators and ObjectIterators
        if (is_array($results) || $results instanceof AbstractCollection) {
            $expectedResultsCount = $this->expectedResultsCount($step);
            switch($expectedResultsCount) {
                case self::$EXPECT_UNSPECIFIED:
                case self::$EXPECT_ANY:
                    break;
                case self::$EXPECT_MANY:
                    if (count($results) == 0) {
                        throw new InvalidMatchResultsNumberException($this->getSelfName() . ' found no matching ' . $this->getResultsName($results) . 's but expects at least one');
                    }
                    break;
                default:
                    $count = count($results);
                    if ($count != $expectedResultsCount) {
                        throw new InvalidMatchResultsNumberException($this->getSelfName() . " found $count matching " . $this->getResultsName($results) . "s but expects $expectedResultsCount");
                    }
            }
        }
    }

    /**
     * @param MigrationStep $step
     * @return int
     * @throws InvalidStepDefinitionException
     */
    protected function expectedResultsType($step)
    {
        switch($this->expectedResultsCount($step)) {
            case 1:
                return self::$RESULT_TYPE_SINGLE;
            case 0:
                // note: we consider '0 results' as a specific case of a multi-valued result set; we might want to
                // to give it its own RESULT_TYPE...
                return self::$RESULT_TYPE_MULTIPLE;
            case self::$EXPECT_UNSPECIFIED:
                return self::$RESULT_TYPE_ANY;
            default: // any, many, 2..N
                return self::$RESULT_TYPE_MULTIPLE;
        }
    }

    /**
     * @param MigrationStep $step
     * @return int|string 'unspecified', 'any', 'many' or a number 0..PHP_MAX_INT
     * @throws InvalidStepDefinitionException
     */
    protected function expectedResultsCount($step)
    {
        if (isset($step->dsl['expect'])) {
            switch ($step->dsl['expect']) {
                case self::$EXPECT_NONE:
                case '0':
                    return 0;
                case self::$EXPECT_ONE:
                case '1':
                    return 1;
                case self::$EXPECT_ANY:
                case self::$EXPECT_MANY:
                    return $step->dsl['expect'];
                default:
                    if ((is_int($step->dsl['expect']) || ctype_digit($step->dsl['expect'])) && $step->dsl['expect'] >= 0) {
                        return (int)$step->dsl['expect'];
                    }
                    throw new InvalidStepDefinitionException("Invalid value for 'expect element: {$step->dsl['expect']}");
            }
        }

        // BC
        if (isset($step->dsl['references_type'])) {
            switch($step->dsl['references_type']) {
                case 'array':
                    return self::$EXPECT_ANY;
                case 'scalar':
                    return 1;
                default:
                    throw new InvalidStepDefinitionException('Unexpected value for references_type element: ' . $step->dsl['references_type']);
            }
        }

        // if there are references to set, except the always_scalar ones, then we want a single result
        // (unless the user told us so via the 'expect' tag)
        if (isset($step->dsl['references']) && $this->hasNonScalarReferences($step->dsl['references'])) {
            return 1;
        }

        return self::$EXPECT_UNSPECIFIED;
    }

    /**
     * @param array $referencesDefinition
     * @return bool
     * @throws InvalidStepDefinitionException
     */
    protected function hasNonScalarReferences($referencesDefinition)
    {
        foreach($referencesDefinition as $key => $referenceDefinition) {
            $referenceDefinition = $this->parseReferenceDefinition($key, $referenceDefinition);
            if (!$this->isScalarReference($referenceDefinition))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $referenceDefinition
     * @return bool
     */
    protected abstract function isScalarReference($referenceDefinition);

    /**
     * Verifies compatibility between the definition of the references to be set and the data set to extract them from.
     * NB: for multivalued/array refs, we assume that the users by default expect at least one value.
     * NB: for scalar results we do not validate anything, as they are always valid (we do not coerce them to arrays)
     * @param AbstractCollection|array|mixed $results
     * @param array $referencesDefinition
     * @param MigrationStep $step
     * @return void throws when incompatibility is found
     * @todo we should encapsulate the whole info about refs to be set in a single data structure, instead of poking inside $step...
     * @deprecated
     */
    /*protected function insureResultsCountCompatibility($results, $referencesDefinition, $step)
    {
        if (!$this->hasNonScalarReferences($referencesDefinition)) {
            return;
        }

        if (is_array($results) || $results instanceof AbstractCollection) {
            if (count($results) > 1 && !$this->allowMultipleResults($step)) {
                throw new \InvalidArgumentException($this->getSelfName() . ' does not support setting references for multiple ' . $this->getResultsName($results) . 's');
            }
            if (count($results) == 0 && !$this->allowEmptyResults($step)) {
                throw new \InvalidArgumentException($this->getSelfName() . ' does not support setting references for no ' . $this->getResultsName($results) . 's');
            }
        }
    }*/

    /**
     * @return string
     */
    protected function getSelfName()
    {
        $className = get_class($this);
        $array = explode('\\', $className);
        $className = end($array);
        // CamelCase to Camel Case using negative look-behind in regexp
        return preg_replace('/(?<!^)[A-Z]/', ' $0', $className);
    }

    /**
     * @param array|object $collection
     * @return string
     */
    protected function getResultsName($collection)
    {
        if (is_array($collection)) {
            return 'result';
        }

        $className = get_class($collection);
        $array = explode('\\', $className);
        $className = str_replace('Collection', '', end($array));
        // CamelCase to snake case using negative look-behind in regexp
        return strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $className));
    }
}

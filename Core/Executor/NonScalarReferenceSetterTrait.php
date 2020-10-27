<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Collection\AbstractCollection;
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

    static $EXPECT_ONE = 'one';
    static $EXPECT_ANY = 'any';
    static $EXPECT_MANY = 'many';

    protected function validateResultsCount($results, $step)
    {
        // q: what if we get a scalar result but we expect an array/collection ?
        if (is_array($results) || $results instanceof AbstractCollection) {
            if (count($results) > 1 && !$this->allowMultipleResults($step)) {
                throw new \InvalidArgumentException($this->getSelfName() . ' found multiple matching ' . $this->getResultsName($results) . 's but expects one');
            }
            if (count($results) == 0 && !$this->allowEmptyResults($step)) {
                throw new \InvalidArgumentException($this->getSelfName() . ' found no matching' . $this->getResultsName($results) . 's but expects at least one');
            }
        }
    }

    /**
     * @param $step
     * @return bool
     */
    protected function allowEmptyResults($step)
    {
        if (isset($step->dsl['expect'])) {
            return ($step->dsl['expect'] == self::$EXPECT_ANY);
        }

        // BC
        if (isset($step->dsl['references_type'])) {
            switch($step->dsl['references_type']) {
                case 'array':
                    return (isset($step->dsl['references_allow_empty']) && $step->dsl['references_allow_empty'] == true);
                case 'scalar':
                    return false;
                default:
                    throw new \InvalidArgumentException('Unexpected value for references_type element: ' . $step->dsl['references_type']);
            }
        }

        // if there are references to set, except the always_scalar ones, then we want a single result
        if (isset($step->dsl['references']) && $this->hasNonScalarReferences($step->dsl['references'])) {
            return false;
        }

        return true;
    }

    protected function allowMultipleResults($step)
    {
        return in_array($this->getResultsType($step), array(self::$RESULT_TYPE_MULTIPLE, self::$RESULT_TYPE_ANY));
    }

    /**
     * @param $step
     * @return int
     */
    protected function getResultsType($step)
    {
        if (isset($step->dsl['expect'])) {
            return ($step->dsl['expect'] == self::$EXPECT_ONE) ? self::$RESULT_TYPE_SINGLE : self::$RESULT_TYPE_MULTIPLE;
        }

        // BC
        if (isset($step->dsl['references_type'])) {
            switch($step->dsl['references_type']) {
                case 'array':
                    return self::$RESULT_TYPE_MULTIPLE;
                case 'scalar':
                    return self::$RESULT_TYPE_SINGLE;
                default:
                    throw new \InvalidArgumentException('Unexpected value for references_type element: ' . $step->dsl['references_type']);
            }
        }

        // if there are references to set, except the always_scalar ones, then we want a single result
        if (isset($step->dsl['references']) && $this->hasNonScalarReferences($step->dsl['references'])) {
            return self::$RESULT_TYPE_SINGLE;
        }

        return self::$RESULT_TYPE_ANY;
    }

    /**
     * @param array $referencesDefinition
     * @return bool
     */
    protected function hasNonScalarReferences($referencesDefinition)
    {
        foreach($referencesDefinition as $referenceDefinition) {
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
     * @param $collection
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

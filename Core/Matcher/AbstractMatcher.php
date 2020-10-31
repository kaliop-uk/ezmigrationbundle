<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use Kaliop\eZMigrationBundle\API\EnumerableMatcherInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchResultsNumberException;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\MatcherInterface;

abstract class AbstractMatcher implements MatcherInterface, EnumerableMatcherInterface
{
    /** @var string[] $allowedConditions the keywords we allow to be used for matching on */
    protected $allowedConditions = array();
    /** @var  string $returns user-readable name of the type of object returned */
    protected $returns;
    /** @var int $maxConditions the maximum number of conditions we allow to match on for a single match request */
    protected $maxConditions = 1;
    /** @var int $minConditions the minimum number of conditions we allow to match on for a single match request. It could be replaced with an array of mandatory conditions, really... */
    protected $minConditions = 1;

    /**
     * @param array $conditions
     * @throws InvalidMatchConditionsException
     */
    protected function validateConditions(array $conditions)
    {
        if ($this->minConditions > 0 && count($conditions) < $this->minConditions) {
            throw new InvalidMatchConditionsException($this->returns . ' can not be matched because the matching conditions are empty');
        }

        if ($this->maxConditions > 0 && count($conditions) > $this->maxConditions) {
            throw new InvalidMatchConditionsException($this->returns . " can not be matched because multiple matching conditions are specified. Only {$this->maxConditions} condition(s) are supported");
        }

        foreach ($conditions as $key => $value) {
            if (!in_array((string)$key, $this->allowedConditions)) {
                throw new InvalidMatchConditionsException($this->returns . " can not be matched because matching condition '$key' is not supported. Supported conditions are: " .
                    implode(', ', $this->allowedConditions));
            }
        }
    }

    /**
     * @return string[]
     */
    public function listAllowedConditions()
    {
        return $this->allowedConditions;
    }

    /**
     * @param $conditionsArray
     * @param bool $tolerateMisses
     * @return array|\ArrayObject
     * @throws InvalidMatchConditionsException
     */
    protected function matchAnd($conditionsArray, $tolerateMisses = false)
    {
        /// @todo introduce proper re-validation of all child conditions
        if (!is_array($conditionsArray) || !count($conditionsArray)) {
            throw new InvalidMatchConditionsException($this->returns . " can not be matched because no matching conditions found for 'and' clause.");
        }

        $class = null;
        foreach ($conditionsArray as $conditions) {
            $out = $this->match($conditions, $tolerateMisses);
            if ($out instanceof \ArrayObject) {
                $class = get_class($out);
                $out = $out->getArrayCopy();
            }
            if (!isset($results)) {
                $results = $out;
            } else {
                $results = array_intersect_key($results, $out);
            }
        }

        if ($class) {
            $results = new $class($results);
        }

        return $results;
    }

    /**
     * @param $conditionsArray
     * @param bool $tolerateMisses
     * @return array
     * @throws InvalidMatchConditionsException
     */
    protected function matchOr($conditionsArray, $tolerateMisses = false)
    {
        /// @todo introduce proper re-validation of all child conditions
        if (!is_array($conditionsArray) || !count($conditionsArray)) {
            throw new InvalidMatchConditionsException($this->returns . " can not be matched because no matching conditions found for 'or' clause.");
        }

        $class = null;
        $results = array();
        foreach ($conditionsArray as $conditions) {
            $out = $this->match($conditions, $tolerateMisses);
            if ($out instanceof \ArrayObject) {
                $class = get_class($out);
                $out = $out->getArrayCopy();
            }
            $results = array_replace($results, $out);
        }

        if ($class) {
            $results = new $class($results);
        }

        return $results;
    }

    /**
     * @param array $conditions
     * @return mixed
     * @throws InvalidMatchConditionsException
     * @throws InvalidMatchResultsNumberException
     */
    public function matchOne(array $conditions)
    {
        $results = $this->match($conditions);
        $count = count($results);
        if ($count !== 1) {
            throw new InvalidMatchResultsNumberException("Found $count " . $this->returns . " when expected exactly only one to match the conditions");
        }

        if ($results instanceof \ArrayObject) {
            $results = $results->getArrayCopy();
        }

        return reset($results);
    }

    /**
     * @param array $conditions
     * @param bool $tolerateMisses
     * @return array|\ArrayObject the keys must be a unique identifier of the matched entities
     * @throws \Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException
     */
    abstract public function match(array $conditions);
}

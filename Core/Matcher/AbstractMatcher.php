<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Repository;
use Kaliop\eZMigrationBundle\API\MatcherInterface;

abstract class AbstractMatcher implements MatcherInterface
{
    /** @var string[] $allowedConditions the keywords we allow to be used for matching on*/
    protected $allowedConditions = array();
    /** @var  string $returns user-readable name of the type of object returned */
    protected $returns;
    /** @var int $maxConditions the maximum number of conditions we allow to match on for a single match request */
    protected $maxConditions = 1;

    protected $repository;

    /**
     * @param Repository $repository
     * @todo inject the services needed, not the whole repository
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    protected function validateConditions(array $conditions)
    {
        if (count($conditions) == 0) {
            throw new \Exception('Content can not be matched because the matching conditions are empty');
        }

        if (count($conditions) > $this->maxConditions) {
            throw new \Exception($this->returns . " can not be matched because multiple matching conditions are specified. Only {this->maxConditions} condition(s) are supported");
        }

        foreach ($conditions as $key => $value) {
            if (!in_array((string)$key, $this->allowedConditions)) {
                throw new \Exception($this->returns . " can not be matched because matching condition '$key' is not supported. Supported conditions are: " .
                    implode(', ', $this->allowedConditions));
            }
        }
    }

    public function matchOne(array $conditions)
    {
        $results = $this->match($conditions);
        $count = count($results);
        if ($count !== 1) {
            throw new \Exception("Found $count " . $this->returns . " when expected exactly only one to match the conditions");
        }
        return reset($results);
    }

    abstract public function match(array $conditions);
}

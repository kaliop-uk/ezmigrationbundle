<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\Core\Matcher\UrlWildcardMatcher;

class UrlWildcardManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('url_wildcard');
    protected $supportedActions = array('create', 'load', 'delete');

    protected $urlWildcardMatcher;

    public function __construct(UrlWildcardMatcher $urlWildcardMatcher)
    {
        $this->urlWildcardMatcher = $urlWildcardMatcher;
    }

    protected function create($step)
    {
        $urlWildcardService = $this->repository->getUrlWildcardService();

        if (!isset($step->dsl['source'])) {
            throw new InvalidStepDefinitionException("The 'source' key is required to create a new urlwildcard.");
        }
        if (!isset($step->dsl['destination'])) {
            throw new InvalidStepDefinitionException("The 'destination' key is required to create a new urlwildcard.");
        }

        $forward = isset($step->dsl['forward']) ? $this->resolveReference($step->dsl['forward']) : false;

        $url = $urlWildcardService->create(
            $this->resolveReference($step->dsl['source']),
            $this->resolveReference($step->dsl['destination']), $forward);

        $this->setReferences($url, $step);

        return $url;
    }

    protected function load($step)
    {
        $urlCollection = $this->matchUrlWildcard('load', $step);

        $this->validateResultsCount($urlCollection, $step);

        $this->setReferences($urlCollection, $step);

        return $urlCollection;
    }

    protected function delete($step)
    {
        $urlCollection = $this->matchUrlWildcard('delete', $step);

        $this->validateResultsCount($urlCollection, $step);

        $this->setReferences($urlCollection, $step);

        $urlWildcardService = $this->repository->getUrlWildcardService();

        foreach ($urlCollection as $url) {
            $urlWildcardService->remove($url);
        }

        return $urlCollection;
    }

    protected function matchUrlWildcard($action, $step)
    {
        if (!isset($step->dsl['match'])) {
            throw new InvalidStepDefinitionException("A match condition is required to $action an urlwildcard");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        $tolerateMisses = isset($step->dsl['match_tolerate_misses']) ? $this->resolveReference($step->dsl['match_tolerate_misses']) : false;

        return $this->urlWildcardMatcher->match($match, $tolerateMisses);
    }

    protected function getReferencesValues($urlWildcard, array $references, $step)
    {
        $refs = array();

        foreach ($references as $key => $reference) {

            $reference = $this->parseReferenceDefinition($key, $reference);

            switch ($reference['attribute']) {
                case 'url_id':
                case 'id':
                    $value = $urlWildcard->id;
                    break;
                case 'source':
                    $value = $urlWildcard->sourceUrl;
                    break;
                case 'destination':
                    $value = $urlWildcard->destinationUrl;
                    break;
                case 'forward':
                    $value = $urlWildcard->forward;
                    break;
                default:
                    throw new InvalidStepDefinitionException('UrlWildcard Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }
}

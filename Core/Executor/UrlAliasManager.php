<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\Core\Matcher\UrlAliasMatcher;

class UrlAliasManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('url_alias');
    protected $supportedActions = array('create', 'load');

    protected $urlAliasMatcher;

    public function __construct(UrlAliasMatcher $urlAliasMatcher)
    {
        $this->urlAliasMatcher = $urlAliasMatcher;
    }

    protected function create($step)
    {
        $urlAliasService = $this->repository->getUrlAliasService();

        if (!isset($step->dsl['destination']) && !isset($step->dsl['destination_location'])) {
            throw new InvalidStepDefinitionException("Either the 'destination' or 'destination_location' key is required to create a new urlalias.");
        }
        if (isset($step->dsl['destination']) && isset($step->dsl['destination_location'])) {
            throw new InvalidStepDefinitionException("The 'destination' and 'destination_location' keys can not be used at the same time to create a new urlalias.");
        }
        // be kind to users
        if (isset($step->dsl['source']) && !isset($step->dsl['path'])) {
            $step->dsl['path'] = $step->dsl['source'];
            unset($step->dsl['source']);
        }
        if (!isset($step->dsl['path'])) {
            throw new InvalidStepDefinitionException("The 'path' key is required to create a new urlalias.");
        }

        $languageCode = isset($step->dsl['language_code']) ? $step->dsl['language_code'] : null;

        $forward = isset($step->dsl['forward']) ? $step->dsl['forward'] : false;

        $alwaysAvailable = isset($step->dsl['always_available']) ? $step->dsl['always_available'] : false;

        if (isset($step->dsl['destination'])) {
            $url = $urlAliasService->createGlobalUrlAlias($step->dsl['destination'], $step->dsl['path'], $languageCode, $forward, $alwaysAvailable);
        } else {
            if (!is_int($step->dsl['destination_location']) && !ctype_digit($step->dsl['destination_location'])) {
                $location = $this->repository->getLocationService()->loadLocationByRemoteId($step->dsl['destination_location']);
            } else {
                $location = $this->repository->getLocationService()->loadLocation($step->dsl['destination_location']);
            }

            $url = $urlAliasService->createUrlAlias($location, $step->dsl['path'], $languageCode, $forward, $alwaysAvailable);
        }


        $this->setReferences($url, $step);

        return $url;
    }

    protected function load($step)
    {
        $urlCollection = $this->matchUrlAlias('load', $step);

        $this->validateResultsCount($urlCollection, $step);

        $this->setReferences($urlCollection, $step);

        return $urlCollection;
    }

    /// @todo allow to delete only the custom aliases of a node
    protected function delete($step)
    {
        $urlCollection = $this->matchUrlAlias('delete', $step);

        $this->validateResultsCount($urlCollection, $step);

        $this->setReferences($urlCollection, $step);

        $urlAliasService = $this->repository->getUrlAliasService();

        foreach ($urlCollection as $url) {
            $urlAliasService->removeAliases([$url]);
        }

        return $urlCollection;
    }

    protected function matchUrlAlias($action, $step)
    {
        if (!isset($step->dsl['match'])) {
            throw new InvalidStepDefinitionException("A match condition is required to $action an urlalias");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        $tolerateMisses = isset($step->dsl['match_tolerate_misses']) ? $this->referenceResolver->resolveReference($step->dsl['match_tolerate_misses']) : false;

        return $this->urlAliasMatcher->match($match, $tolerateMisses);
    }

    protected function getReferencesValues($urlAlias, array $references, $step)
    {
        $refs = array();

        foreach ($references as $key => $reference) {

            $reference = $this->parseReferenceDefinition($key, $reference);

            switch ($reference['attribute']) {
                case 'url_id':
                case 'id':
                    $value = $urlAlias->id;
                    break;
                case 'type':
                    $value = $urlAlias->type;
                    break;
                case 'destination':
                    $value = $urlAlias->destination;
                    break;
                case 'path':
                case 'source':
                    $value = $urlAlias->path;
                    break;
                case 'language_codes':
                    /// @todo return a string?
                    $value = $urlAlias->languageCodes;
                    break;
                case 'always_available':
                    $value = $urlAlias->alwaysAvailable;
                    break;
                case 'is_history':
                    $value = $urlAlias->isHistory;
                    break;
                case 'is_custom':
                    $value = $urlAlias->isCustom;
                    break;
                case 'forward':
                    $value = $urlAlias->forward;
                    break;
                default:
                    throw new InvalidStepDefinitionException('UrlAlias Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }
}

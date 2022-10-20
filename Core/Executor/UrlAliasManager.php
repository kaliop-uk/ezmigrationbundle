<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\Core\Matcher\UrlAliasMatcher;

/**
 * @todo allow refreshing non-custom location aliases
 */
class UrlAliasManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('url_alias');
    protected $supportedActions = array('create', 'load', 'cleanup', 'regenerate');

    protected $urlAliasMatcher;
    protected $locationManager;

    public function __construct(UrlAliasMatcher $urlAliasMatcher, LocationManager $locationManager)
    {
        $this->urlAliasMatcher = $urlAliasMatcher;
        $this->locationManager = $locationManager;
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
        if (!isset($step->dsl['source']) && !isset($step->dsl['path'])) {
            throw new InvalidStepDefinitionException("The 'source' key is required to create a new urlalias.");
        }
        if (isset($step->dsl['source'])) {
            $path = $this->resolveReference($step->dsl['source']);
        } else {
            $path = $this->resolveReference($step->dsl['path']);
        }

        $languageCode = isset($step->dsl['language_code']) ? $this->resolveReference($step->dsl['language_code']) : null;

        $forward = isset($step->dsl['forward']) ? $this->resolveReference($step->dsl['forward']) : false;

        $alwaysAvailable = isset($step->dsl['always_available']) ? $this->resolveReference($step->dsl['always_available']) : false;

        if (isset($step->dsl['destination'])) {
            $url = $urlAliasService->createGlobalUrlAlias($this->resolveReference($step->dsl['destination']), $path, $languageCode, $forward, $alwaysAvailable);
        } else {
            /// @todo Should we always resolve refs in $step->dsl['destination_location']? What if it is a remote_id in the form of 'reference:hello'
            $destination = $this->resolveReference($step->dsl['destination_location']);

            if (!is_int($destination) && !ctype_digit($destination)) {
                $location = $this->repository->getLocationService()->loadLocationByRemoteId($destination);
            } else {
                $location = $this->repository->getLocationService()->loadLocation($destination);
            }

            $url = $urlAliasService->createUrlAlias($location, $path, $languageCode, $forward, $alwaysAvailable);
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

    protected function regenerate($step)
    {
        $urlAliasService = $this->repository->getUrlAliasService();

        foreach ($this->locationManager->matchLocations('refresh the system url aliases for', $step) as $location)
        {
            $urlAliasService->refreshSystemUrlAliasesForLocation($location);
        }

        return true;
    }

    protected function cleanup($step)
    {
        $urlAliasService = $this->repository->getUrlAliasService();

        return $urlAliasService->deleteCorruptedUrlAliases();
    }

    protected function matchUrlAlias($action, $step)
    {
        if (!isset($step->dsl['match'])) {
            throw new InvalidStepDefinitionException("A match condition is required to $action an urlalias");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        $tolerateMisses = isset($step->dsl['match_tolerate_misses']) ? $this->resolveReference($step->dsl['match_tolerate_misses']) : false;

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
                    /// @todo should we return a string?
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

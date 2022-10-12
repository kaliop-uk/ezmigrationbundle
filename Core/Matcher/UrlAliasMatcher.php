<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\Content\UrlAlias;
use Kaliop\eZMigrationBundle\API\Collection\UrlAliasCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

/**
 * @todo allow matching non-custom location aliases via matchUrlAlias()
 */
class UrlAliasMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_URL_ID = 'url_id'; // NB: this is used to match a composite string $parentid-$md5, not the id column in the DB
    const MATCH_URL = 'url';
    const MATCH_LOCATION_ID = 'location_id';
    const MATCH_LOCATION_REMOTE_ID = 'location_remote_id';

    protected $allowedConditions = array(
        self::MATCH_ALL, self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_URL_ID, self::MATCH_URL, self::MATCH_LOCATION_ID, self::MATCH_LOCATION_REMOTE_ID,
        // aliases
        'id'
    );
    protected $returns = 'UrlALias';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return UrlAliasCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function match(array $conditions, $tolerateMisses = false)
    {
        return $this->matchUrlAlias($conditions, $tolerateMisses);
    }

    /**
     * NB: by default, only custom aliases will be matched for locations
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return UrlAliasCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function matchUrlAlias(array $conditions, $tolerateMisses = false)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_URL_ID:
                    return new UrlAliasCollection($this->findUrlAliasesById($values, $tolerateMisses));

                case self::MATCH_URL:
                    return new UrlAliasCollection($this->findUrlAliasesByUrl($values, $tolerateMisses));

                case self::MATCH_LOCATION_ID:
                case self::MATCH_LOCATION_REMOTE_ID:
                    return new UrlAliasCollection($this->findUrlAliasesByLocation($values, $tolerateMisses));

                case self::MATCH_ALL:
                    /// @todo this will most likely not surface custom location aliases
                    return new UrlAliasCollection($this->findAllUrlAliases());

                case self::MATCH_AND:
                    return $this->matchAnd($values, $tolerateMisses = false);

                case self::MATCH_OR:
                    return $this->matchOr($values, $tolerateMisses = false);

                case self::MATCH_NOT:
                    /// @todo this will most likely not surface custom location aliases
                    return new UrlAliasCollection(array_diff_key($this->findAllUrlAliases(), $this->matchUrlAlias($values, true)->getArrayCopy()));
            }
        }
    }

    protected function getConditionsFromKey($key)
    {
        // The value for url_id matching is a string, so hard to tell it apart from matching eg. by url
        return array(self::MATCH_URL_ID => $key);
    }

    /**
     * @param string[] $urlIds
     * @param bool $tolerateMisses
     * @return UrlAlias[]
     * @throws NotFoundException
     */
    protected function findUrlAliasesById(array $urlIds, $tolerateMisses = false)
    {
        $urls = [];

        foreach ($urlIds as $urlId) {
            try {
                // return unique items
                $UrlAlias = $this->repository->getUrlAliasService()->load($urlId);
                $urls[$UrlAlias->id] = $UrlAlias;
            } catch (NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $urls;
    }

    /**
     * @param string[] $urls
     * @param bool $tolerateMisses
     * @return UrlAlias[]
     * @throws NotFoundException
     */
    protected function findUrlAliasesByUrl(array $urls, $tolerateMisses = false)
    {
        $urls = [];

        foreach ($urls as $url) {
            try {
                // return unique contents
                $UrlAlias = $this->repository->getUrlAliasService()->lookup($url);
                $urls[$UrlAlias->id] = $UrlAlias;
            } catch (NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $urls;
    }

    /**
     * @param int[]|string[] $locationIds
     * @param bool $tolerateMisses
     * @param bool|null $custom when null, return both custom and non-custom aliases. When false, only non-custom
     * @return UrlAlias[]
     * @throws NotFoundException
     */
    protected function findUrlAliasesByLocation(array $locationIds, $tolerateMisses = false, $custom = true)
    {
        $urls = [];

        foreach ($locationIds as $locationId) {
            try {
                if (!is_int($locationId) && !ctype_digit($locationId)) {
                    // presume it is a remote_id
                    $location = $this->repository->getLocationService()->loadLocationByRemoteId($locationId);
                } else {
                    $location = $this->repository->getLocationService()->loadLocation($locationId);
                }
                // return unique items
                if ($custom === null || !$custom) {
                    foreach ($this->repository->getUrlAliasService()->listLocationAliases($location, false) as $UrlAlias) {
                        $urls[$UrlAlias->id] = $UrlAlias;
                    }
                }
                if ($custom === null || $custom) {
                    foreach ($this->repository->getUrlAliasService()->listLocationAliases($location) as $UrlAlias) {
                        $urls[$UrlAlias->id] = $UrlAlias;
                    }
                }
            } catch (NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $urls;
    }

    /**
     * @todo check: does this include all custom location aliases? If not, we should not allow matching with NOT
     * @return UrlAlias[]
     */
    protected function findAllUrlAliases()
    {
        $urls = [];

        foreach ($this->repository->getUrlAliasService()->listGlobalAliases() as $UrlAlias) {
            // return unique contents
            $urls[$UrlAlias->id] = $UrlAlias;
        }

        return $urls;
    }
}

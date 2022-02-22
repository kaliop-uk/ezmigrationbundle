<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\Content\UrlAlias;
use Kaliop\eZMigrationBundle\API\Collection\UrlAliasCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

class UrlAliasMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_URL_ID = 'url_id';
    const MATCH_URL = 'url';

    protected $allowedConditions = array(
        self::MATCH_ALL, self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_URL_ID, self::MATCH_URL,
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

                case self::MATCH_ALL:
                    return new UrlAliasCollection($this->findAllUrlAliases());

                case self::MATCH_AND:
                    return $this->matchAnd($values, $tolerateMisses = false);

                case self::MATCH_OR:
                    return $this->matchOr($values, $tolerateMisses = false);

                case self::MATCH_NOT:
                    return new UrlAliasCollection(array_diff_key($this->findAllUrlAliases(), $this->matchUrlAlias($values, true)->getArrayCopy()));
            }
        }
    }

    protected function getConditionsFromKey($key)
    {
        /// @todo should we allow to match by url ?
        //if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_URL_ID => $key);
        //}
    }

    /**
     * @param int[] $urlIds
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
            } catch(NotFoundException $e) {
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
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $urls;
    }

    /**
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

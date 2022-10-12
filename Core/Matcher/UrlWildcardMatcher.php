<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\Content\UrlWildcard;
use Kaliop\eZMigrationBundle\API\Collection\UrlWildcardCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

class UrlWildcardMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_URL_ID = 'url_id';

    protected $allowedConditions = array(
        self::MATCH_ALL, self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_URL_ID,
        // aliases
        'id'
    );
    protected $returns = 'UrlWildcard';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return UrlWildcardCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function match(array $conditions, $tolerateMisses = false)
    {
        return $this->matchUrlWildcard($conditions, $tolerateMisses);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return UrlWildcardCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function matchUrlWildcard(array $conditions, $tolerateMisses = false)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_URL_ID:
                    return new UrlWildcardCollection($this->findUrlWildcardsById($values, $tolerateMisses));

                case self::MATCH_ALL:
                    return new UrlWildcardCollection($this->findAllUrlWildcards());

                case self::MATCH_AND:
                    return $this->matchAnd($values, $tolerateMisses = false);

                case self::MATCH_OR:
                    return $this->matchOr($values, $tolerateMisses = false);

                case self::MATCH_NOT:
                    return new UrlWildcardCollection(array_diff_key($this->findAllUrlWildcards(), $this->matchUrlWildcard($values, true)->getArrayCopy()));
            }
        }
    }

    protected function getConditionsFromKey($key)
    {
        //if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_URL_ID => $key);
        //}
    }

    /**
     * @param int[] $urlIds
     * @param bool $tolerateMisses
     * @return UrlWildcard[]
     * @throws NotFoundException
     */
    protected function findUrlWildcardsById(array $urlIds, $tolerateMisses = false)
    {
        $urls = [];

        foreach ($urlIds as $urlId) {
            try {
                // return unique contents
                $urlWildcard = $this->repository->getURLWildcardService()->load($urlId);
                $urls[$urlWildcard->id] = $urlWildcard;
            } catch (NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $urls;
    }

    /**
     * @return UrlWildcard[]
     */
    protected function findAllUrlWildcards()
    {
        $urls = [];

        foreach ($this->repository->getURLWildcardService()->loadAll() as $urlWildcard) {
            // return unique contents
            $urls[$urlWildcard->id] = $urlWildcard;
        }

        return $urls;
    }
}

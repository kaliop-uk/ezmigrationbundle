<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\Content\Language;
use Kaliop\eZMigrationBundle\API\Collection\LanguageCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;

class LanguageMatcher extends RepositoryMatcher implements KeyMatcherInterface
{
    use FlexibleKeyMatcherTrait;

    const MATCH_LANGUAGE_ID = 'language_id';
    const MATCH_LANGUAGE_CODE = 'language_code';

    protected $allowedConditions = array(
        self::MATCH_ALL, self::MATCH_AND, self::MATCH_OR, self::MATCH_NOT,
        self::MATCH_LANGUAGE_ID, self::MATCH_LANGUAGE_CODE,
        // aliases
        'id', 'language'
    );
    protected $returns = 'Language';

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return LanguageCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function match(array $conditions, $tolerateMisses = false)
    {
        return $this->matchLanguage($conditions, $tolerateMisses);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @param bool $tolerateMisses
     * @return LanguageCollection
     * @throws InvalidMatchConditionsException
     * @throws NotFoundException
     */
    public function matchLanguage(array $conditions, $tolerateMisses = false)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_LANGUAGE_ID:
                    return new LanguageCollection($this->findLanguagesById($values, $tolerateMisses));

                case 'langauge_code':
                case self::MATCH_LANGUAGE_CODE:
                    return new LanguageCollection($this->findLanguagesByCode($values, $tolerateMisses));

                case self::MATCH_ALL:
                    return new LanguageCollection($this->findAllLanguages());

                case self::MATCH_AND:
                    return $this->matchAnd($values, $tolerateMisses = false);

                case self::MATCH_OR:
                    return $this->matchOr($values, $tolerateMisses = false);

                case self::MATCH_NOT:
                    return new LanguageCollection(array_diff_key($this->findAllLanguages(), $this->matchLanguage($values, true)->getArrayCopy()));
            }
        }
    }

    protected function getConditionsFromKey($key)
    {
        if (is_int($key) || ctype_digit($key)) {
            return array(self::MATCH_LANGUAGE_ID => $key);
        }
        return array(self::MATCH_LANGUAGE_CODE => $key);
    }

    /**
     * @param int[] $languageIds
     * @param bool $tolerateMisses
     * @return Language[]
     * @throws NotFoundException
     */
    protected function findLanguagesById(array $languageIds, $tolerateMisses = false)
    {
        $languages = [];

        foreach ($languageIds as $languageId) {
            try {
                // return unique contents
                $language = $this->repository->getContentLanguageService()->loadLanguageById($languageId);
                $languages[$language->id] = $language;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $languages;
    }

    /**
     * @param string[] $languageIdentifiers
     * @param bool $tolerateMisses
     * @return Language[]
     * @throws NotFoundException
     */
    protected function findLanguagesByCode(array $languageIdentifiers, $tolerateMisses = false)
    {
        $languages = [];

        foreach ($languageIdentifiers as $languageIdentifier) {
            try {
                // return unique contents
                $language = $this->repository->getContentLanguageService()->loadLanguage($languageIdentifier);
                $languages[$language->id] = $language;
            } catch(NotFoundException $e) {
                if (!$tolerateMisses) {
                    throw $e;
                }
            }
        }

        return $languages;
    }

    /**
     * @return Language[]
     */
    protected function findAllLanguages()
    {
        $languages = [];

        foreach ($this->repository->getContentLanguageService()->loadLanguages() as $language) {
            // return unique contents
            $languages[$language->id] = $language;
        }

        return $languages;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\Matcher;

use eZ\Publish\API\Repository\Values\Content\Language;
use Kaliop\eZMigrationBundle\API\Collection\LanguageCollection;
use Kaliop\eZMigrationBundle\API\KeyMatcherInterface;
use Kaliop\eZMigrationBundle\API\Exception\InvalidMatchConditionsException;

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
     * @return LanguageCollection
     * @throws InvalidMatchConditionsException
     */
    public function match(array $conditions)
    {
        return $this->matchLanguage($conditions);
    }

    /**
     * @param array $conditions key: condition, value: int / string / int[] / string[]
     * @return LanguageCollection
     * @throws InvalidMatchConditionsException
     */
    public function matchLanguage(array $conditions)
    {
        $this->validateConditions($conditions);

        foreach ($conditions as $key => $values) {

            if (!is_array($values)) {
                $values = array($values);
            }

            switch ($key) {
                case 'id':
                case self::MATCH_LANGUAGE_ID:
                    return new LanguageCollection($this->findLanguagesById($values));

                case 'langauge_code':
                case self::MATCH_LANGUAGE_CODE:
                    return new LanguageCollection($this->findLanguagesByCode($values));

                case self::MATCH_ALL:
                    return new LanguageCollection($this->findAllLanguages());

                case self::MATCH_AND:
                    return $this->matchAnd($values);

                case self::MATCH_OR:
                    return $this->matchOr($values);

                case self::MATCH_NOT:
                    return new LanguageCollection(array_diff_key($this->findAllLanguages(), $this->matchLanguage($values)->getArrayCopy()));
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
     * @return Language[]
     */
    protected function findLanguagesById(array $languageIds)
    {
        $languages = [];

        foreach ($languageIds as $languageId) {
            // return unique contents
            $language = $this->repository->getContentLanguageService()->loadLanguageById($languageId);
            $languages[$language->id] = $language;
        }

        return $languages;
    }

    /**
     * @param string[] $languageIdentifiers
     * @return Language[]
     */
    protected function findLanguagesByCode(array $languageIdentifiers)
    {
        $languages = [];

        foreach ($languageIdentifiers as $languageIdentifier) {
            // return unique contents
            $language = $this->repository->getContentLanguageService()->loadLanguage($languageIdentifier);
            $languages[$language->id] = $language;
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

<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\Content\Language;
use Kaliop\eZMigrationBundle\API\Collection\LanguageCollection;
use Kaliop\eZMigrationBundle\API\Exception\InvalidStepDefinitionException;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\API\EnumerableMatcherInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\LanguageMatcher;

/**
 * Handles language migrations.
 */
class LanguageManager extends RepositoryExecutor implements MigrationGeneratorInterface, EnumerableMatcherInterface
{
    protected $supportedStepTypes = array('language');
    protected $supportedActions = array('create', 'load', 'update', 'delete');

    /** @var LanguageMatcher $languageMatcher */
    protected $languageMatcher;

    /**
     * @param LanguageMatcher $languageMatcher
     */
    public function __construct(LanguageMatcher $languageMatcher)
    {
        $this->languageMatcher = $languageMatcher;
    }

    /**
     * Handles the language create migration action
     *
     * @todo allow creating disabkled languages
     */
    protected function create($step)
    {
        $languageService = $this->repository->getContentLanguageService();

        if (!isset($step->dsl['lang'])) {
            throw new InvalidStepDefinitionException("The 'lang' key is required to create a new language.");
        }

        $languageCreateStruct = $languageService->newLanguageCreateStruct();
        $languageCreateStruct->languageCode = $this->resolveReference($step->dsl['lang']);
        if (isset($step->dsl['name'])) {
            $languageCreateStruct->name = $this->resolveReference($step->dsl['name']);
        }
        if (isset($step->dsl['enabled'])) {
            $languageCreateStruct->enabled = (bool)$this->resolveReference($step->dsl['enabled']);
        }
        $language = $languageService->createLanguage($languageCreateStruct);

        $this->setReferences($language, $step);

        return $language;
    }

    protected function load($step)
    {
        $languageCollection = $this->matchLanguages('load', $step);

        $this->validateResultsCount($languageCollection, $step);

        $this->setReferences($languageCollection, $step);

        return $languageCollection;
    }

    /**
     * Handles the language update migration action
     */
    protected function update($step)
    {
        if (isset($step->dsl['lang'])) {
            // BC
            $step->dsl['match'] = array('language_code' => $step->dsl['lang']);
        }

        $languageCollection = $this->matchLanguages('delete', $step);

        $this->validateResultsCount($languageCollection, $step);

        $languageService = $this->repository->getContentLanguageService();

        foreach ($languageCollection as $key => $language) {

            if (isset($step->dsl['name'])) {
                $languageService->updateLanguageName($language, $this->resolveReference($step->dsl['name']));
            }

            if (isset($step->dsl['enabled'])) {
                if ($this->resolveReference($step->dsl['enabled'])) {
                    $languageService->enableLanguage($language);
                } else {
                    $languageService->disableLanguage($language);
                };
            }

            $languageCollection[$key] = $languageService->loadLanguageById($key);
        }

        $this->setReferences($languageCollection, $step);

        return $languageCollection;
    }

    /**
     * Handles the language delete migration action
     */
    protected function delete($step)
    {
        if (isset($step->dsl['lang'])) {
            // BC
            $step->dsl['match'] = array('language_code' => $step->dsl['lang']);
        }

        $languageCollection = $this->matchLanguages('delete', $step);

        $this->validateResultsCount($languageCollection, $step);

        $this->setReferences($languageCollection, $step);

        $languageService = $this->repository->getContentLanguageService();

        foreach ($languageCollection as $language) {
            $languageService->deleteLanguage($language);
        }

        return $languageCollection;
    }

    /**
     * @param string $action
     * @return LanguageCollection
     * @throws \Exception
     */
    protected function matchLanguages($action, $step)
    {
        if (!isset($step->dsl['match'])) {
            throw new InvalidStepDefinitionException("A match condition is required to $action a language");
        }

        // convert the references passed in the match
        $match = $this->resolveReferencesRecursively($step->dsl['match']);

        $tolerateMisses = isset($step->dsl['match_tolerate_misses']) ? $this->resolveReference($step->dsl['match_tolerate_misses']) : false;

        return $this->languageMatcher->match($match, $tolerateMisses);
    }

    /**
     * @param Language $language
     * @param array $references the definitions of the references to set
     * @throws InvalidStepDefinitionException
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($language, array $references, $step)
    {
        $refs = array();

        foreach ($references as $key => $reference) {

            $reference = $this->parseReferenceDefinition($key, $reference);

            switch ($reference['attribute']) {
                case 'language_id':
                case 'id':
                    $value = $language->id;
                    break;
                case 'enabled':
                    $value = $language->enabled;
                    break;
                case 'language_code':
                    $value = $language->languageCode;
                    break;
                case 'language_name':
                case 'name':
                    $value = $language->name;
                    break;
                default:
                    throw new InvalidStepDefinitionException('Language Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }

    /**
     * @param array $matchConditions
     * @param string $mode
     * @param array $context
     * @return array
     * @throws \Exception
     */
    public function generateMigration(array $matchConditions, $mode, array $context = array())
    {
        $data = array();
        $previousUserId = $this->loginUser($this->getAdminUserIdentifierFromContext($context));
        try {
            $languageCollection = $this->languageMatcher->match($matchConditions);

            /** @var \eZ\Publish\API\Repository\Values\Content\Language $language */
            foreach ($languageCollection as $language) {

                $languageData = array(
                    'type' => reset($this->supportedStepTypes),
                    'mode' => $mode,
                );

                switch ($mode) {
                    case 'create':
                        $languageData = array_merge(
                            $languageData,
                            array(
                                'lang' => $language->languageCode,
                                'name' => $language->name,
                                'enabled' => $language->enabled
                            )
                        );
                        break;
                    case 'update':
                        $languageData = array_merge(
                            $languageData,
                            array(
                                'match' => array(
                                    LanguageMatcher::MATCH_LANGUAGE_ID => $language->id
                                ),
                                'lang' => $language->languageCode,
                                'name' => $language->name,
                                'enabled' => $language->enabled
                            )
                        );
                        break;
                    case 'delete':
                        $languageData = array_merge(
                            $languageData,
                            array(
                                'match' => array(
                                    LanguageMatcher::MATCH_LANGUAGE_ID => $language->id
                                )
                            )
                        );
                        break;
                    default:
                        throw new InvalidStepDefinitionException("Executor 'language' doesn't support mode '$mode'");
                }

                $data[] = $languageData;
            }

            $this->loginUser($previousUserId);
        } catch (\Exception $e) {
            $this->loginUser($previousUserId);
            throw $e;
        }

        return $data;
    }

    /**
     * @return string[]
     */
    public function listAllowedConditions()
    {
        return $this->languageMatcher->listAllowedConditions();
    }
}

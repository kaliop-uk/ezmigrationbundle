<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\Content\Language;

/**
 * Handles language migrations.
 */
class LanguageManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('language');
    protected $supportedActions = array('create', 'update', 'delete', 'upsert');

    /**
     * Handles the language create migration action
     */
    protected function create($step)
    {
        foreach (array('lang', 'name') as $key) {
            if (!isset($step->dsl[$key])) {
                throw new \Exception("The '$key' key is missing in a language creation definition");
            }
        }
        $languageService = $this->repository->getContentLanguageService();
        $languageCreateStruct = $languageService->newLanguageCreateStruct();
        $languageCreateStruct->languageCode = $step->dsl['lang'];
        $languageCreateStruct->name = $step->dsl['name'];
        if (isset($step->dsl['enabled'])) {
            $languageCreateStruct->enabled = (bool)$step->dsl['enabled'];
        }
        $language = $languageService->createLanguage($languageCreateStruct);

        $this->setReferences($language, $step);

        return $language;
    }

    /**
     * Handles the language update migration action
     *
     * @todo use a matcher for flexible matching?
     */
    protected function update($step)
    {
        if (!isset($step->dsl['lang'])) {
            throw new \Exception("The 'lang' key is required to update a language.");
        }

        $languageService = $this->repository->getContentLanguageService();
        $language = $languageService->loadLanguage($step->dsl['lang']);

        if (isset($step->dsl['name'])) {
            $languageService->updateLanguageName($language, $step->dsl['name']);
        }
        if (isset($step->dsl['enabled'])) {
            if ((bool)$step->dsl['enabled']) {
                $languageService->enableLanguage($language);
            } else {
                $languageService->disableLanguage($language);
            }
        }

        $this->setReferences($language, $step);

        return $language;
    }

    /**
     * Handles the language delete migration action
     *
     * @todo use a matcher for flexible matching?
     */
    protected function delete($step)
    {
        if (!isset($step->dsl['lang'])) {
            throw new \Exception("The 'lang' key is required to delete a language.");
        }

        $languageService = $this->repository->getContentLanguageService();
        $language = $languageService->loadLanguage($step->dsl['lang']);

        $languageService->deleteLanguage($language);

        return $language;
    }

    /**
     * Method that create a language if it doesn't already exist.
     */
    protected function upsert($step)
    {
        foreach (array('lang', 'name') as $key) {
            if (!isset($step->dsl[$key])) {
                throw new \Exception("The '$key' key is missing in a language upsert definition");
            }
        }

        $languageService = $this->repository->getContentLanguageService();

        try {
            $languageService->loadLanguage($step->dsl['lang']);

            return $this->update($step);
        } catch (NotFoundException $e) {
            return $this->create($step);
        }
    }

    /**
     * @param Language $language
     * @param array $references the definitions of the references to set
     * @throws \InvalidArgumentException When trying to assign a reference to an unsupported attribute
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues($language, array $references, $step)
    {
        $refs = array();

        foreach ($references as $reference) {

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
                    throw new \InvalidArgumentException('Language Manager does not support setting references for attribute ' . $reference['attribute']);
            }

            $refs[$reference['identifier']] = $value;
        }

        return $refs;
    }
}

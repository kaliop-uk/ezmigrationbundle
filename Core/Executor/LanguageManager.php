<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

use eZ\Publish\API\Repository\Values\Content\Language;
use Kaliop\eZMigrationBundle\API\Collection\LanguageCollection;

/**
 * Handles language migrations.
 */
class LanguageManager extends RepositoryExecutor
{
    protected $supportedStepTypes = array('language');
    protected $supportedActions = array('create', 'delete');

    /**
     * Handles the language create migration action
     */
    protected function create($step)
    {
        $languageService = $this->repository->getContentLanguageService();

        if (!isset($step->dsl['lang'])) {
            throw new \Exception("The 'lang' key is required to create a new language.");
        }

        $languageCreateStruct = $languageService->newLanguageCreateStruct();
        $languageCreateStruct->languageCode = $step->dsl['lang'];
        if (isset($step->dsl['name'])) {
            $languageCreateStruct->name = $step->dsl['name'];
        }
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
        throw new \Exception('Language update is not implemented yet');

        /*$languageService = $this->repository->getContentLanguageService();

        if (!isset($step->dsl['lang'])) {
            throw new \Exception("The 'lang' key is required to update a language.");
        }

        $this->setReferences($language, $step);*/
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
     * @param Language $language
     * @param array $references the definitions of the references to set
     * @throws \InvalidArgumentException When trying to assign a reference to an unsupported attribute
     * @return array key: the reference names, values: the reference values
     */
    protected function getReferencesValues(Language $language, array $references)
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

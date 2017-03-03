<?php

namespace Kaliop\eZMigrationBundle\Core\Executor;

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
    protected function create()
    {
        $languageService = $this->repository->getContentLanguageService();

        if (!isset($this->dsl['lang'])) {
            throw new \Exception("The 'lang' key is required to create a new language.");
        }

        $languageCreateStruct = $languageService->newLanguageCreateStruct();
        $languageCreateStruct->languageCode = $this->dsl['lang'];
        if (isset($this->dsl['name'])) {
            $languageCreateStruct->name = $this->dsl['name'];
        }
        if (isset($this->dsl['enabled'])) {
            $languageCreateStruct->enabled = (bool)$this->dsl['enabled'];
        }
        $language = $languageService->createLanguage($languageCreateStruct);

        $this->setReferences($language);

        return $language;
    }

    /**
     * Handles the language update migration action
     *
     * @todo use a matcher for flexible matching?
     */
    protected function update()
    {
        throw new \Exception('Language update is not implemented yet');

        /*$languageService = $this->repository->getContentLanguageService();

        if (!isset($this->dsl['lang'])) {
            throw new \Exception("The 'lang' key is required to update a language.");
        }

        $this->setReferences($language);*/
    }

    /**
     * Handles the language delete migration action
     */
    protected function delete()
    {
        if (!isset($this->dsl['lang'])) {
            throw new \Exception("The 'lang' key is required to delete a language.");
        }

        $languageService = $this->repository->getContentLanguageService();
        $language = $languageService->loadLanguage($this->dsl['lang']);

        $languageService->deleteLanguage($language);

        return $language;
    }

    /**
     * Sets references to certain language attributes.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Language|LanguageCollection $language
     * @throws \InvalidArgumentException When trying to set a reference to an unsupported attribute
     * @return boolean
     */
    protected function setReferences($language)
    {
        if (!array_key_exists('references', $this->dsl)) {
            return false;
        }

        if ($language instanceof LanguageCollection) {
            if (count($language) > 1) {
                throw new \InvalidArgumentException('Language Manager does not support setting references for creating/updating of multiple languages');
            }
            $language = reset($language);
        }

        foreach ($this->dsl['references'] as $reference) {

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

            $this->referenceResolver->addReference($reference['identifier'], $value);
        }

        return true;
    }
}

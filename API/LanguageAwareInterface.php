<?php

namespace Kaliop\eZMigrationBundle\API;

interface LanguageAwareInterface
{
    /**
     * Injects language code, with xxx-YY format.
     * e.g. fre-FR
     *
     * @param string $languageCode
     */
    public function setLanguageCode($languageCode);

    /**
     * @return string
     */
    public function getLanguageCode();

    /**
     * Sets default language code, with xxx-YY format.
     * e.g. fre-FR
     *
     * @param string $languageCode
     */
    public function setDefaultLanguageCode($languageCode);

    /**
     * @return string
     */
    public function getDefaultLanguageCode();
}

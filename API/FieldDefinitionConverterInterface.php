<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Interface for field handlers that handle translation of ContentType Field definition settings
 */
interface FieldDefinitionConverterInterface
{
    /**
     * Converts the ContentType field settings as gotten from the repo into the hash for the migration definition
     *
     * @param mixed $settingsValue The ContentType field settings as gotten from the repo
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return mixed the array / scalar which will can be saved in a migration definition
     */
    public function fieldSettingsToHash($settingsValue, array $context = array());

    /**
     * Converts the ContentType field settings as gotten from the migration definition into something the repo can understand
     *
     * @param mixed $settingsHash The ContentType field settings hash as gotten from the migration definition
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return mixed the array / scalar / obj usable as field settings in a ContentType create/update struct
     */
    public function hashToFieldSettings($settingsHash, array $context = array());
}

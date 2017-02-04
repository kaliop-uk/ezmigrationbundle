<?php

namespace Kaliop\eZMigrationBundle\API;

/**
 * Interface those complex fields that handle translation of ContentType field settings
 */
interface FieldSettingsHandlerInterface
{
    /**
     *
     * @param $settingsValue The ContentType field settings as gotten from the repo
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return mixed the array / scalar which will can be saved in a migration definition
     */
    public function fieldSettingsToHash($settingsValue, array $context = array());

    /**
     * Return a non primitive field value - or a primitive field value with custom transformation
     *
     * @param $settingsHash The ContentType field settings hash as gotten from the migration definition
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return mixed the array / scalar / obj usable as field settings in a ContentType create/update struct
     */
    public function hashToFieldSettings($settingsHash, array $context = array());
}

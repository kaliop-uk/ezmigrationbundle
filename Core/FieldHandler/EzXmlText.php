<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;
use Kaliop\eZMigrationBundle\API\FieldDefinitionConverterInterface;
use Kaliop\eZMigrationBundle\API\EmbeddedReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

class EzXmlText extends AbstractFieldHandler implements FieldValueImporterInterface, FieldDefinitionConverterInterface
{
    public function setReferenceResolver(ReferenceResolverInterface $referenceResolver)
    {
        if (! $referenceResolver instanceof EmbeddedReferenceResolverInterface) {
            throw new MigrationBundleException("Reference resolver injected into EzXmlText field handler should implement EmbeddedReferenceResolverInterface");
        }
        parent::setReferenceResolver($referenceResolver);
    }

    /**
     * Replaces any references in an xml string to be used as the input data for an ezxmltext field.
     *
     * @param string|array $fieldValue The definition of teh field value, structured in the yml file. Either a string, or an array with key 'content'
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return string
     *
     * @todo replace objects and location refs in eznode and ezobject links
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        if (is_string($fieldValue)) {
            $xmlText = $fieldValue;
        } else if (is_array($fieldValue) && isset($fieldValue['xml'])) {
            // native export format from eZ
            $xmlText = $fieldValue['xml'];
        } else {
            $xmlText = $fieldValue['content'];
        }

        // Check if there are any references in the xml text and replace them. Please phpstorm.
        $resolver = $this->referenceResolver;
        /** @var EmbeddedReferenceResolverInterface $resolver */
        return $resolver->resolveEmbeddedReferences($xmlText);
    }

    public function fieldSettingsToHash($settingsValue, array $context = array())
    {
        // work around https://jira.ez.no/browse/EZP-26916
        if (is_array($settingsValue) && isset($settingsValue['tagPreset'])) {
            /// @todo this conversion should be based on the value of TagPresets ini legacy setting in ezxml.ini,
            ///       keeping in mind that at migration execution time only values 0 and 1 are supported anyway...
            if ($settingsValue['tagPreset'] == 'mini') {
                $settingsValue['tagPreset'] = 1;
            }
            $settingsValue['tagPreset'] = (integer)$settingsValue['tagPreset'];
        }
        return $settingsValue;
    }

    public function hashToFieldSettings($settingsHash, array $context = array())
    {
        return $settingsHash;
    }
}

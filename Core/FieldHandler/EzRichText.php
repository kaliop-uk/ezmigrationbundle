<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;
use Kaliop\eZMigrationBundle\API\EmbeddedReferenceResolverInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationBundleException;
use Kaliop\eZMigrationBundle\API\ReferenceResolverInterface;

class EzRichText extends AbstractFieldHandler implements FieldValueImporterInterface
{
    public function setReferenceResolver(ReferenceResolverInterface $referenceResolver)
    {
        if (! $referenceResolver instanceof EmbeddedReferenceResolverInterface) {
            throw new MigrationBundleException("Reference resolver injected into EzRichText field handler should implement EmbeddedReferenceResolverInterface");
        }
        parent::setReferenceResolver($referenceResolver);
    }

    /**
     * Replaces any references in an xml string to be used as the input data for an ezrichtext field.
     *
     * @param string|array $fieldValue The definition of teh field value, structured in the yml file. Either a string, or an array with key 'content'
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return string
     *
     * @todo replace objects and location refs in ezcontent:// and ezlocation:// links
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
}

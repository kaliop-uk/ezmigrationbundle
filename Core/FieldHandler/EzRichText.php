<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;
use Kaliop\eZMigrationBundle\API\EmbeddedReferenceResolverInterface;
use Kaliop\eZMigrationBundle\Core\ReferenceResolver\PrefixBasedResolverInterface;

/// @todo unify $this->resolver and $this->referenceResolver (are they the same already ???)
class EzRichText extends AbstractFieldHandler implements FieldValueImporterInterface
{
    protected $resolver;

    /**
     * @param PrefixBasedResolverInterface $resolver must implement EmbeddedReferenceResolverInterface, really
     */
    public function __construct(PrefixBasedResolverInterface $resolver)
    {
        $this->resolver = $resolver;
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
        } else {
            $xmlText = $fieldValue['content'];
        }

        // Check if there are any references in the xml text and replace them.
        // Check if there are any references in the xml text and replace them.
        if (!$this->resolver instanceof EmbeddedReferenceResolverInterface) {
            throw new \Exception("Reference resolver passed to HTTPExecutor should implement EmbeddedReferenceResolverInterface");
        }

        return $this->resolver->ResolveEmbeddedReferences($xmlText);
    }
}

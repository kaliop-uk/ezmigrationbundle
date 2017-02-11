<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;
use Kaliop\eZMigrationBundle\Core\ReferenceResolver\PrefixBasedResolverInterface;

/// @todo unify $this->resolver and $this->referenceResolver
class EzRichText extends AbstractComplexField implements FieldValueImporterInterface
{
    protected $resolver;

    public function __construct(PrefixBasedResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Replace any references in an xml string to be used as the input data for an ezrichtext field.
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

        // we need to alter the regexp we get from the resolver, as it will be used to match parts of text, not the whole string
        $regexp = substr($this->resolver->getRegexp(), 1, -1);
        // NB: here we assume that all regexp resolvers give us a regexp with a very specific format...
        $regexp = '/\[' . preg_replace(array('/^\^/'), array('', ''), $regexp) . '[^]]+\]/';

        $count = preg_match_all($regexp, $xmlText, $matches);
        // $matches[0][] will have the matched full string eg.: [reference:example_reference]
        if ($count) {
            foreach ($matches[0] as $referenceIdentifier) {
                $reference = $this->resolver->getReferenceValue(substr($referenceIdentifier, 1, -1));
                $xmlText = str_replace($referenceIdentifier, $reference, $xmlText);
            }
        }

        return $xmlText;
    }
}

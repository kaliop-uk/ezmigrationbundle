<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;
use Kaliop\eZMigrationBundle\Core\Matcher\LocationMatcher;

class EzRichText extends AbstractComplexField implements ComplexFieldInterface
{
    protected $contentMatcher;
    protected $locationMatcher;

    public function __construct(ContentMatcher $contentMatcher, LocationMatcher $locationMatcher)
    {
        $this->contentMatcher = $contentMatcher;
        $this->locationMatcher = $locationMatcher;
    }

    /**
     * Replace any references in an xml string to be used as the input data for an ezrichtext field.
     *
     * @param array $fieldValueArray The definition of teh field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return string
     *
     * @todo replace objects and location refs in ezcontent:// and ezlocation:// links
     */
    public function createValue(array $fieldValueArray, array $context = array())
    {
        $xmlText = $fieldValueArray['content'];

        /// @todo this regexp belongs to the resolver...

        //Check if there are any references in the xml text and replace them.
        // $result[0][] will have the matched full string eg.: [reference:example_reference]
        // $result[1][] will have the reference id eg.: example_reference
        $count = preg_match_all('|\[(reference:[^\]\[]*)\]|', $xmlText, $result);

        if ($count !== false && count($result) > 1) {
            foreach ($result[1] as $index => $referenceIdentifier) {
                $reference = $this->referenceResolver->getReferenceValue($referenceIdentifier);

                $xmlText = str_replace($result[0][$index], $reference, $xmlText);
            }
        }

        return $xmlText;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use eZ\Publish\Core\FieldType\RelationList\Value;
use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;

class EzRelationList extends AbstractComplexField implements ComplexFieldInterface
{
    protected $contentMatcher;

    public function __construct(ContentMatcher $contentMatcher)
    {
        $this->contentMatcher = $contentMatcher;
    }

    /**
     * @param array $fieldValueArray The definition of the field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return Value
     */
    public function createValue($fieldValueArray, array $context = array())
    {
        if (array_key_exists('destinationContentIds', $fieldValueArray)) {
            // fromHash format
            $ids = $fieldValueArray['destinationContentIds'];
        } elseif (is_array($fieldValueArray)) {
            // simplified format
            $ids = $fieldValueArray;
        } else {
            $ids = array();
        }

        foreach ($ids as $key => $id) {
            // 1. resolve relations
            $ids[$key] = $this->referenceResolver->resolveReference($id);
            // 2. resolve remote ids
            $ids[$key] = $this->contentMatcher->matchOneByKey($ids[$key])->id;
        }

        return new Value($ids);
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use eZ\Publish\Core\FieldType\RelationList\Value;
use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;
use Kaliop\eZMigrationBundle\Core\Matcher\ContentMatcher;

class EzRelationList extends AbstractFieldHandler implements FieldValueImporterInterface
{
    protected $contentMatcher;

    public function __construct(ContentMatcher $contentMatcher)
    {
        $this->contentMatcher = $contentMatcher;
    }

    /**
     * @param array $fieldValue The definition of the field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return Value
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        if (count($fieldValue) == 1 && isset($fieldValue['destinationContentIds'])) {
            // fromHash format
            $ids = $fieldValue['destinationContentIds'];
        } else if ($fieldValue === null) {
            $ids = array();
        } else {
            // simplified format
            $ids = $fieldValue;
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

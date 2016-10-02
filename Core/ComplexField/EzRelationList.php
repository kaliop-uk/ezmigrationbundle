<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use eZ\Publish\Core\FieldType\RelationList\Value;
use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;

class EzRelationList extends AbstractComplexField implements ComplexFieldInterface
{
    /**
     * @param array $fieldValueArray The definition of the field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return Value
     */
    public function createValue(array $fieldValueArray, array $context = array())
    {
        if (count($fieldValueArray) == 1 && isset($fieldValueArray['destinationContentIds'])) {
            // fromHash format
            $ids = $fieldValueArray['destinationContentIds'];
        } else {
            // simplified format
            $ids = $fieldValueArray;
        }

        foreach ($ids as $key => $id) {
            if ($this->referenceResolver->isReference($id)) {
                $ids[$key] = $this->referenceResolver->getReferenceValue($id);
            }
        }

        return new Value($ids);
    }
}

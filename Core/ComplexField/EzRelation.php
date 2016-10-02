<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use eZ\Publish\Core\FieldType\Relation\Value;
use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;

class EzRelation extends AbstractComplexField implements ComplexFieldInterface
{
    /**
     * Creates a value object to use as the field value when setting an ez page field type.
     *
     * @param array $fieldValueArray The definition of the field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return Value
     */
    public function createValue(array $fieldValueArray, array $context = array())
    {
        $id = $fieldValueArray['destinationContentId'];

        if ($this->referenceResolver->isReference($id)) {
            $id = $this->referenceResolver->getReferenceValue($id);
        }

        return new Value($id);
    }
}

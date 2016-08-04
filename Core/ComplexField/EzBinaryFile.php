<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use eZ\Publish\Core\FieldType\BinaryFile\Value as BinaryFileValue;
use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;

class EzBinaryFile extends AbstractComplexField implements ComplexFieldInterface
{
    /**
     * @param array $fieldValueArray The definition of teh field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return BinaryFileValue
     */
    public function createValue(array $fieldValueArray, array $context = array())
    {
        $filePath = dirname($context['path']) .  '/files/' . $fieldValueArray['path'];

        return new BinaryFileValue(
            array(
                'path' => $filePath,
                'fileSize' => filesize($filePath),
                'fileName' => basename($filePath),
                'mimeType' => mime_content_type($filePath)
            )
        );
    }
}

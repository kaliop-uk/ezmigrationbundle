<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use eZ\Publish\Core\FieldType\Image\Value as ImageValue;
use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;

class EzImage extends AbstractComplexField implements ComplexFieldInterface
{
    /**
     * Creates a value object to use as the field value when setting an image field type.
     *
     * @param array $fieldValueArray The definition of teh field value, structured in the yml file
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return ImageValue
     */
    public function createValue(array $fieldValueArray, array $context = array())
    {
        $filePath = dirname($context['path']) . '/images/' . $fieldValueArray['path'];
        $altText = array_key_exists('alt_text', $fieldValueArray) ? $fieldValueArray['alt_text'] : '';

        return new ImageValue(
            array(
                'path' => $filePath,
                'fileSize' => filesize($filePath),
                'fileName' => basename($filePath),
                'alternativeText' => $altText
            )
        );
    }
}

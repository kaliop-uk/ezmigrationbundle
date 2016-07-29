<?php

namespace Kaliop\eZMigrationBundle\Core\API\ComplexFields;

use eZ\Publish\Core\FieldType\Image\Value as ImageValue;

class EzImage extends AbstractComplexField
{
    /**
     * Creates a value object to use as the field value when setting an image field type.
     *
     * @return ImageValue
     */
    public function createValue()
    {
        $imageData = $this->fieldValueArray;
        $migrationDir = $this->container->getParameter('ez_migration_bundle.version_directory');
        $bundlePath = $this->bundle->getPath();
        $filePath = $bundlePath . '/' . $migrationDir . '/images/' . $imageData['path'];
        $altText = array_key_exists('alt_text', $imageData) ? $imageData['alt_text'] : '';

        $value = new ImageValue(
            array(
                'path' => $filePath,
                'fileSize' => filesize($filePath),
                'fileName' => basename($filePath),
                'alternativeText' => $altText
            )
        );

        return $value;
    }

    protected function createImageValue(array $imageData)
    {
    }
}

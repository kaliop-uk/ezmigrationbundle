<?php
/**
 * Created by PhpStorm.
 * User: daniel
 * Date: 11/12/15
 * Time: 15:04
 */

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
        $migrationDir = $this->container->getParameter('kaliop_bundle_migration.version_directory');
        $bundlePath = $this->bundle->getPath();
        $filePath = $bundlePath . '/' . $migrationDir . '/images/' . $imageData['path'];

        $value = new ImageValue(
            array(
                'path' => $filePath,
                'fileSize' => filesize($filePath),
                'fileName' => basename($filePath),
                'alternativeText' => $imageData['alt_text']
            )
        );

        return $value;
    }


    protected function createImageValue(array $imageData)
    {
    }

}
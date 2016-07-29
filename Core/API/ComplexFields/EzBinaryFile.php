<?php

namespace Kaliop\eZMigrationBundle\Core\API\ComplexFields;

use eZ\Publish\Core\FieldType\BinaryFile\Value as BinaryFileValue;

class EzBinaryFile extends AbstractComplexField
{
    public function createValue()
    {
        $fileData = $this->fieldValueArray;
        $migrationDir = $this->container->getParameter('kaliop_bundle_migration.version_directory');
        $bundlePath = $this->bundle->getPath();
        $filePath = $bundlePath . '/' . $migrationDir .  '/files/' . $fileData['path'];

        $value = new BinaryFileValue(
            array(
                'path' => $filePath,
                'fileSize' => filesize($filePath),
                'fileName' => basename($filePath),
                'mimeType' => mime_content_type($filePath)
            )
        );

        return $value;
    }
}

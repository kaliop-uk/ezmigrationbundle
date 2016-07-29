<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use eZ\Publish\Core\FieldType\BinaryFile\Value as BinaryFileValue;
use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;

class EzBinaryFile extends AbstractComplexField implements ComplexFieldInterface
{
    public function createValue()
    {
        $fileData = $this->fieldValueArray;
        $migrationDir = $this->container->getParameter('ez_migration_bundle.version_directory');
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

<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use eZ\Publish\Core\FieldType\BinaryFile\Value as BinaryFileValue;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;

class EzBinaryFile extends FileFieldHandler implements FieldValueConverterInterface
{
    /**
     * @param array|string $fieldValue The path to the file or an array with 'path' key
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return BinaryFileValue
     *
     * @todo resolve refs more
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        $mimeType = '';
        $fileName = '';

        if ($fieldValue === null) {
            return new BinaryFileValue();
        } if (is_string($fieldValue)) {
            $filePath = $fieldValue;
        } else {
            $filePath = $fieldValue['path'];
            if (isset($fieldValue['filename'])) {
                $fileName = $fieldValue['filename'];
            }
            if (isset($fieldValue['mime_type'])) {
                $mimeType = $fieldValue['mime_type'];
            }
        }

        // default format: path is relative to the 'files' dir
        $realFilePath = dirname($context['path']) . '/files/' . $filePath;

        // but in the past, when using a string, this worked as well as an absolute path, so we have to support it as well
        if (!is_file($realFilePath) && is_file($filePath)) {
            $realFilePath = $filePath;
        }

        return new BinaryFileValue(
            array(
                'path' => $realFilePath,
                'fileSize' => filesize($realFilePath),
                'fileName' => $fileName != '' ? $fileName : basename($realFilePath),
                'mimeType' => $mimeType != '' ? $mimeType : mime_content_type($realFilePath)
            )
        );
    }

    /**
     * @param \eZ\Publish\Core\FieldType\BinaryFile\Value $fieldValue
     * @param array $context
     * @return array
     *
     * @todo check if this works in ezplatform
     */
    public function fieldValueToHash($fieldValue, array $context = array())
    {
        if ($fieldValue->uri == null) {
            return null;
        }

        return array(
            'path' => realpath($this->ioRootDir) . '/' . ($this->ioDecorator ? $this->ioDecorator->undecorate($fieldValue->uri) : $fieldValue->uri),
            'filename'=> $fieldValue->fileName,
            'mimeType' => $fieldValue->mimeType
        );
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use eZ\Publish\Core\FieldType\Media\Value as MediaValue;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;

class EzMedia extends FileFieldHandler implements FieldValueConverterInterface
{
    /**
     * Creates a value object to use as the field value when setting a media field type.
     *
     * @param array|string $fieldValue The path to the file or an array with 'path' and many other keys
     * @param array $context The context for execution of the current migrations. Contains f.e. the path to the migration
     * @return MediaValue
     *
     * @todo resolve refs more
     */
    public function hashToFieldValue($fieldValue, array $context = array())
    {
        $fileName = '';
        $mimeType = '';
        $hasController = false;
        $autoPlay = false;
        $loop = false;
        $height = 0;
        $width = 0;

        if ($fieldValue === null) {
            return new MediaValue();
        } else if (is_string($fieldValue)) {
            $filePath = $fieldValue;
        } else {
            // BC
            if (isset($fieldValue['fileName'])) {
                $fileName = $this->referenceResolver->resolveReference($fieldValue['fileName']);
            }
            if (isset($fieldValue['mimeType'])) {
                $fileName = $this->referenceResolver->resolveReference($fieldValue['mimeType']);
            }
            if (isset($fieldValue['hasController'])) {
                $hasController = $this->referenceResolver->resolveReference($fieldValue['hasController']);
            }
            if (isset($fieldValue['inputUri'])) {
                $filePath = $this->referenceResolver->resolveReference($fieldValue['inputUri']);
            } else {
                $filePath = $this->referenceResolver->resolveReference($fieldValue['path']);
            }
            // new attribute names
            if (isset($fieldValue['filename'])) {
                $fileName = $this->referenceResolver->resolveReference($fieldValue['filename']);
            }
            if (isset($fieldValue['has_controller'])) {
                $hasController = $this->referenceResolver->resolveReference($fieldValue['has_controller']);
            }
            if (isset($fieldValue['autoplay'])) {
                $autoPlay = $this->referenceResolver->resolveReference($fieldValue['autoplay']);
            }
            if (isset($fieldValue['loop'])) {
                $loop = $this->referenceResolver->resolveReference($fieldValue['loop']);
            }
            if (isset($fieldValue['height'])) {
                $height = $this->referenceResolver->resolveReference($fieldValue['height']);
            }
            if (isset($fieldValue['width'])) {
                $width = $this->referenceResolver->resolveReference($fieldValue['width']);
            }
            if (isset($fieldValue['mime_type'])) {
                $mimeType = $this->referenceResolver->resolveReference($fieldValue['mime_type']);
            }
        }

        // 'default' format: path is relative to the 'media' dir
        $realFilePath = dirname($context['path']) . '/media/' . $filePath;

        // but in the past, when using a string, this worked as well as an absolute path, so we have to support it as well
        /// @todo atm this does not work for files from content fields in cluster mode
        if (!is_file($realFilePath) && is_file($filePath)) {
            $realFilePath = $filePath;
        }

        return new MediaValue(
            array(
                'path' => $realFilePath,
                'fileSize' => filesize($realFilePath),
                'fileName' => $fileName != '' ? $fileName : basename($realFilePath),
                'mimeType' => $mimeType != '' ? $mimeType : mime_content_type($realFilePath),
                'hasController' => $hasController,
                'autoplay' => $autoPlay,
                'loop'=> $loop,
                'height' => $height,
                'width' => $width,
            )
        );
    }

    /**
     * @param \eZ\Publish\Core\FieldType\Media\Value $fieldValue
     * @param array $context
     * @return array
     */
    public function fieldValueToHash($fieldValue, array $context = array())
    {
        if ($fieldValue->uri == null) {
            return null;
        }
        $binaryFile = $this->ioService->loadBinaryFile($fieldValue->id);
        /// @todo we should handle clustered configurations, to give back the absolute path on disk rather than the 'virtual' one
        return array(
            'path' => realpath($this->ioRootDir) . '/' . ($this->ioDecorator ? $this->ioDecorator->undecorate($binaryFile->uri) : $fieldValue->uri),
            'filename'=> $fieldValue->fileName,
            'mime_type' => $fieldValue->mimeType,
            'has_controller' => $fieldValue->hasController,
            'autoplay' => $fieldValue->autoplay,
            'loop'=> $fieldValue->loop,
            'width' => $fieldValue->width,
            'height' => $fieldValue->height,
        );
    }
}

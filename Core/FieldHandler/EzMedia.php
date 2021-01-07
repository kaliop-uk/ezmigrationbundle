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
                $fileName = $fieldValue['fileName'];
            }
            if (isset($fieldValue['mimeType'])) {
                $fileName = $fieldValue['mimeType'];
            }
            if (isset($fieldValue['hasController'])) {
                $hasController = $fieldValue['hasController'];
            }
            if (isset($fieldValue['inputUri'])) {
                $filePath = $fieldValue['inputUri'];
            } else {
                $filePath = $fieldValue['path'];
            }
            // new attribute names
            if (isset($fieldValue['filename'])) {
                $fileName = $fieldValue['filename'];
            }
            if (isset($fieldValue['has_controller'])) {
                $hasController = $fieldValue['has_controller'];
            }
            if (isset($fieldValue['autoplay'])) {
                $autoPlay = $fieldValue['autoplay'];
            }
            if (isset($fieldValue['loop'])) {
                $loop = $fieldValue['loop'];
            }
            if (isset($fieldValue['height'])) {
                $height = $fieldValue['height'];
            }
            if (isset($fieldValue['width'])) {
                $width = $fieldValue['width'];
            }
            if (isset($fieldValue['mime_type'])) {
                $mimeType = $fieldValue['mime_type'];
            }
        }

        // 'default' format: path is relative to the 'media' dir
        $realFilePath = dirname($context['path']) . '/media/' . $filePath;

        // but in the past, when using a string, this worked as well as an absolute path, so we have to support it as well
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
     *
     * @todo check out if this works in ezplatform
     */
    public function fieldValueToHash($fieldValue, array $context = array())
    {
        if ($fieldValue->uri == null) {
            return null;
        }
        $binaryFile = $this->ioService->loadBinaryFile($fieldValue->id);
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

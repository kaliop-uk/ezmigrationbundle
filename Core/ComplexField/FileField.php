<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use eZ\Publish\Core\IO\UrlDecorator;

class FileField extends AbstractComplexField
{
    protected $ioRootDir;
    protected $ioDecorator;

    public function __construct($ioRootDir, UrlDecorator $ioDecorator=null)
    {
        $this->ioRootDir = $ioRootDir;
        $this->ioDecorator = $ioDecorator;
    }
}

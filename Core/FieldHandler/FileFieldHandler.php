<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use eZ\Publish\Core\IO\UrlDecorator;

class FileFieldHandler extends AbstractFieldHandler
{
    protected $ioRootDir;
    protected $ioDecorator;

    public function __construct($ioRootDir, UrlDecorator $ioDecorator=null)
    {
        $this->ioRootDir = $ioRootDir;
        $this->ioDecorator = $ioDecorator;
    }
}

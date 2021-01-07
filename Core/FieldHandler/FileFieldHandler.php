<?php

namespace Kaliop\eZMigrationBundle\Core\FieldHandler;

use eZ\Publish\Core\IO\UrlDecorator;

class FileFieldHandler extends AbstractFieldHandler
{
    protected $ioRootDir;
    protected $ioDecorator;
    protected $ioService;

    public function __construct($ioRootDir, UrlDecorator $ioDecorator=null, $ioService=null)
    {
        $this->ioRootDir = $ioRootDir;
        $this->ioDecorator = $ioDecorator;
        $this->ioService = $ioService;
    }
}

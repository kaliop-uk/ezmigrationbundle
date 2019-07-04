<?php

namespace Kaliop\eZMigrationBundle\API\Event;

use Symfony\Component\EventDispatcher\Event;

class MigrationGeneratedEvent extends Event
{
    protected $type;
    protected $mode;
    protected $format;
    protected $data;
    protected $file;
    protected $context;
    protected $matchCondition;

    public function __construct($type, $mode, $format, $data, $file, $matchCondition = null, $context = null)
    {
        $this->type = $type;
        $this->mode = $mode;
        $this->format = $format;
        $this->data = $data;
        $this->file = $file;
        $this->matchCondition = $matchCondition;
        $this->context = $context;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function getFormat()
    {
        return $this->format;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getMatchCondition()
    {
        return $this->matchCondition;
    }

    public function replaceData($data)
    {
        $this->data = $data;
    }

    public function replaceFile($fileName)
    {
        $this->file = $fileName;
    }
}

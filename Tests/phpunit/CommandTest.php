<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\BufferedOutput;

abstract class CommandTest extends WebTestCase
{
    protected $dslDir;
    protected $targetBundle = 'EzPublishCoreBundle'; // it is always present :-)
    protected $leftovers = array();

    protected $container;
    protected $app;
    protected $output;


    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        // seems like this can not be used outside of the constructor...
        $this->dslDir = __DIR__ . '/../dsl';
    }
    protected function setUp()
    {
        $this->container = $this->getContainer();

        $this->app = new Application(static::$kernel);
        $this->app->setAutoExit(false);
        $this->output = new BufferedOutput();
        $this->leftovers = array();
    }

    protected function tearDown()
    {
        foreach($this->leftovers as $file) {
            unlink($file);
        }

        // clean buffer, just in case...
        $this->output->fetch();
    }

    protected function getContainer()
    {
        if (null !== static::$kernel) {
            static::$kernel->shutdown();
        }
        // run in our own test environment. Sf by default uses the 'test' one. We let phpunit.xml set it...
        $options = array(
            'environment' => $_SERVER['SYMFONY_ENV']
        );
        static::$kernel = static::createKernel($options);
        static::$kernel->boot();
        return static::$kernel->getContainer();
    }
}

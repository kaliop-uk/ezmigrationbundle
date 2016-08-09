<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\StreamOutput;

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
        $fp = fopen('php://temp', 'r+');
        $this->output = new StreamOutput($fp);
        $this->leftovers = array();
    }

    /**
     * Fetches the data from the output buffer, resetting it.
     * It would be nice to use BufferedOutput, but that is not available in Sf 2.3...
     * @return null|string
     */
    protected function fetchOutput()
    {
        if (!$this->output) {
            return null;
        }

        $fp = $this->output->getStream();
        rewind($fp);
        $out = stream_get_contents($fp);

        fclose($fp);
        $fp = fopen('php://temp', 'r+');
        $this->output = new StreamOutput($fp);

        return $out;
    }

    protected function tearDown()
    {
        foreach($this->leftovers as $file) {
            unlink($file);
        }

        // clean buffer, just in case...
        if ($this->output) {
            $fp = $this->output->getStream();
            fclose($fp);
            $this->output = null;
        }
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

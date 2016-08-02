<?php

namespace Kaliop\eZMigrationBundle\Core\Helper;

use Symfony\Component\Console\Event\ConsoleCommandEvent;

/**
 * 'Saves' the console IO channels to make them available to whoever needs them, eg. kinky php migrations...
 */
class ConsoleIO
{
    protected $input;
    protected $output;

    public function onConsoleCommand(ConsoleCommandEvent $event) {
        $this->input = $event->getInput();
        $this->output = $event->getOutput();
    }

    /**
     * NB: will return NULL when called from anything else but a console application context!
     * @return \Symfony\Component\Console\Input\InputInterface
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * NB: will return NULL when called from anything else but a console application context!
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }
}

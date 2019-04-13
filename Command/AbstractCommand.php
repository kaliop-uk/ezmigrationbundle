<?php

namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Kaliop\eZMigrationBundle\Core\MigrationService;

/**
 * Base command class that all migration commands extend from.
 */
abstract class AbstractCommand extends ContainerAwareCommand
{
    /**
     * @var MigrationService
     */
    private $migrationService;

    /** @var OutputInterface $output */
    protected $output;
    /** @var OutputInterface $output */
    protected $errOutput;
    protected $verbosity = OutputInterface::VERBOSITY_NORMAL;

    /**
     * @return MigrationService
     */
    public function getMigrationService()
    {
        if (!$this->migrationService) {
            $this->migrationService = $this->getContainer()->get('ez_migration_bundle.migration_service');
        }

        return $this->migrationService;
    }

    protected function setOutput(OutputInterface $output)
    {
        $this->output = $output;
        $this->errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }

    protected function setVerbosity($verbosity)
    {
        $this->verbosity = $verbosity;
    }

    /**
     * Small trick to allow us to:
     * - lower verbosity between NORMAL and QUIET
     * - have a decent writeln API, even with old SF versions
     * @param string|array $message The message as an array of lines or a single string
     * @param int $verbosity
     */
    protected function writeln($message, $verbosity = OutputInterface::VERBOSITY_NORMAL)
    {
        if ($this->verbosity >= $verbosity) {
            $this->output->writeln($message);
        }
    }
}

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
     * @param int $type
     */
    protected function writeln($message, $verbosity = OutputInterface::VERBOSITY_NORMAL, $type = OutputInterface::OUTPUT_NORMAL)
    {
        if ($this->verbosity >= $verbosity) {
            $this->output->writeln($message, $type);
        }
    }

    /**
     * @param string|array $message The message as an array of lines or a single string
     * @param int $verbosity
     * @param int $type
     */
    protected function writeErrorln($message, $verbosity = OutputInterface::VERBOSITY_QUIET, $type = OutputInterface::OUTPUT_NORMAL)
    {
        if ($this->verbosity >= $verbosity) {

            // When verbosity is set to quiet, SF swallows the error message in the writeln call
            // (unlike for other verbosity levels, which are left for us to handle...)
            // We resort to a hackish workaround to _always_ print errors to stdout, even in quiet mode.
            // If the end user does not want any error echoed, he can just 2>/dev/null
            if ($this->errOutput->getVerbosity() == OutputInterface::VERBOSITY_QUIET) {
                $this->errOutput->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
                $this->errOutput->writeln($message, $type);
                $this->errOutput->setVerbosity(OutputInterface::VERBOSITY_QUIET);
            }
            else
            {
                $this->errOutput->writeln($message, $type);
            }
        }
    }

    /**
     * "Canonicalizes" the paths which are subpaths of the application's root dir
     * @param string[] $paths
     * @return string[]
     */
    protected function normalizePaths($paths)
    {
        $rootDir = realpath($this->getContainer()->get('kernel')->getRootDir() . '/..') . '/';
        foreach ($paths as $i => $path) {
            if ($path === $rootDir || $path === './') {
                $paths[$i] = './';
            } else if (strpos($path, './') === 0) {
                $paths[$i] = substr($path, 2);
            // q: should we also call realpath on $path? what if there are symlinks at play?
            } elseif (strpos($path, $rootDir) === 0) {
                $paths[$i] = substr($path, strlen($rootDir));
            } elseif ($path === '') {
                unset($paths[$i]);
            }
        }
        return $paths;
    }
}

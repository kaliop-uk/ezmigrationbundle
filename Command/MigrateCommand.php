<?php

namespace Kaliop\eZMigrationBundle\Command;

use Kaliop\eZMigrationBundle\Core\MigrationService;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Command to execute the available migration definitions.
 */
class MigrateCommand extends AbstractCommand
{
    // in between QUIET and NORMAL
    const VERBOSITY_CHILD = 0.5;
    /** @var OutputInterface $output */
    protected $output;
    protected $verbosity = OutputInterface::VERBOSITY_NORMAL;

    const COMMAND_NAME = 'kaliop:migration:migrate';

    /**
     * Set up the command.
     *
     * Define the name, options and help text.
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName(self::COMMAND_NAME)
            ->setAliases(array('kaliop:migration:update'))
            ->setDescription('Execute available migration definitions.')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "The directory or file to load the migration definitions from")
            // nb: when adding options, remember to forward them to sub-commands executed in 'separate-process' mode
            ->addOption('default-language', 'l', InputOption::VALUE_REQUIRED, "Default language code that will be used if no language is provided in migration steps")
            ->addOption('admin-login', 'a', InputOption::VALUE_REQUIRED, "Login of admin account used whenever elevated privileges are needed (user id 14 used by default)")
            ->addOption('ignore-failures', 'i', InputOption::VALUE_NONE, "Keep executing migrations even if one fails")
            ->addOption('clear-cache', 'c', InputOption::VALUE_NONE, "Clear the cache after the command finishes")
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, "Do not ask any interactive question")
            ->addOption('no-transactions', 'u', InputOption::VALUE_NONE, "Do not use a repository transaction to wrap each migration. Unsafe, but needed for legacy slot handlers")
            ->addOption('separate-process', 'p', InputOption::VALUE_NONE, "Use a separate php process to run each migration. Safe if your migration leak memory. A tad slower")
            ->addOption('child', null, InputOption::VALUE_NONE, "*DO NOT USE* Internal option for when forking separate processes")
            ->setHelp(<<<EOT
The <info>kaliop:migration:migrate</info> command loads and executes migrations:

    <info>./ezpublish/console kaliop:migration:migrate</info>

You can optionally specify the path to migration definitions with <info>--path</info>:

    <info>./ezpublish/console kaliop:migrations:migrate --path=/path/to/bundle/version_directory --path=/path/to/bundle/version_directory/single_migration_file</info>
EOT
            );
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null|int null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);

        $this->setOutput($output);
        $this->setVerbosity($output->getVerbosity());

        if ($input->getOption('child')) {
            $this->setVerbosity(self::VERBOSITY_CHILD);
        }

        $this->getContainer()->get('ez_migration_bundle.step_executed_listener.tracing')->setOutput($output);

        $migrationService = $this->getMigrationService();

        $toExecute = $this->buildMigrationsList($input->getOption('path'), $migrationService);

        if (!count($toExecute)) {
            $output->writeln('<info>No migrations to execute</info>');
            return 0;
        }

        $this->printMigrationsList($toExecute, $input, $output);

        // ask user for confirmation to make changes
        if (!$this->askForConfirmation($input, $output)) {
            return 0;
        }

        if ($input->getOption('separate-process')) {
            $builder = new ProcessBuilder();
            $executableFinder = new PhpExecutableFinder();
            if (false !== $php = $executableFinder->find()) {
                $builder->setPrefix($php);
            }
            // mandatory args and options
            $builderArgs = array(
                $_SERVER['argv'][0], // sf console
                self::COMMAND_NAME, // name of sf command. Can we get it from the Application instead of hardcoding?
                '--env=' . $this->getContainer()->get('kernel')->getEnvironment(), // sf env
                '--child'
            );
            // 'optional' options
            // note: options 'clear-cache', 'ignore-failures' and 'no-transactions' we never propagate
            if ($input->getOption('default-language')) {
                $builderArgs[] = '--default-language=' . $input->getOption('default-language');
            }
            if ($input->getOption('no-transactions')) {
                $builderArgs[] = '--no-transactions';
            }
        }

        $executed = 0;
        $failed = 0;
        $skipped = 0;

        /** @var MigrationDefinition $migrationDefinition */
        foreach ($toExecute as $name => $migrationDefinition) {

            // let's skip migrations that we know are invalid - user was warned and he decided to proceed anyway
            if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
                $output->writeln("<comment>Skipping $name</comment>\n");
                $skipped++;
                continue;
            }

            $this->writeln("<info>Processing $name</info>");

            if ($input->getOption('separate-process')) {

                $process = $builder
                    ->setArguments(array_merge($builderArgs, array('--path=' . $migrationDefinition->path)))
                    ->getProcess();

                $this->writeln('<info>Executing: ' . $process->getCommandLine() . '</info>', OutputInterface::VERBOSITY_VERBOSE);

                // allow long migrations processes by default
                $process->setTimeout(86400);
                // and give immediate feedback to the user
                $process->run(
                    function($type, $buffer) {
                        echo $buffer;
                    }
                );

                try {

                    if (!$process->isSuccessful()) {
                        throw new \Exception($process->getErrorOutput());
                    }

                    // There are cases where the separate process dies halfway but does not return a non-zero code.
                    // That's why we should double-check here if the migration is still tagged as 'started'...
                    /** @var Migration $migration */
                    $migration = $migrationService->getMigration($migrationDefinition->name);

                    if (!$migration) {
                        // q: shall we add the migration to the db as failed? In doubt, we let it become a ghost, disappeared without a trace...
                        throw new \Exception("After the separate process charged to execute the migration finished, the migration can not be found in the database any more.");
                    } else if ($migration->status == Migration::STATUS_STARTED) {
                        $errorMsg = "The separate process charged to execute the migration left it in 'started' state. Most likely it died halfway through execution.";
                        $migrationService->endMigration(New Migration(
                            $migration->name,
                            $migration->md5,
                            $migration->path,
                            $migration->executionDate,
                            Migration::STATUS_FAILED,
                            ($migration->executionError != '' ? ($errorMsg . ' ' . $migration->executionError) : $errorMsg)
                        ));
                        throw new \Exception($errorMsg);
                    }

                    $executed++;

                } catch (\Exception $e) {
                    if ($input->getOption('ignore-failures')) {
                        $output->writeln("\n<error>Migration failed! Reason: " . $e->getMessage() . "</error>\n");
                        $failed++;
                        continue;
                    }
                    $output->writeln("\n<error>Migration aborted! Reason: " . $e->getMessage() . "</error>");
                    return 1;
                }

            } else {

                try {
                    $migrationService->executeMigration(
                        $migrationDefinition,
                        !$input->getOption('no-transactions'),
                        $input->getOption('default-language'),
                        $input->getOption('admin-login')
                    );
                    $executed++;
                } catch (\Exception $e) {
                    if ($input->getOption('ignore-failures')) {
                        $output->writeln("\n<error>Migration failed! Reason: " . $e->getMessage() . "</error>\n");
                        $failed++;
                        continue;
                    }
                    $output->writeln("\n<error>Migration aborted! Reason: " . $e->getMessage() . "</error>");
                    return 1;
                }

            }
        }

        if ($input->getOption('clear-cache')) {
            $command = $this->getApplication()->find('cache:clear');
            $inputArray = new ArrayInput(array('command' => 'cache:clear'));
            $command->run($inputArray, $output);
        }

        $time = microtime(true) - $start;
        $this->writeln("Executed $executed migrations, failed $failed, skipped $skipped");
        if ($input->getOption('separate-process')) {
            // in case of using subprocesses, we can not measure max memory used
            $this->writeln("Time taken: ".sprintf('%.2f', $time)." secs");
        } else {
            $this->writeln("Time taken: ".sprintf('%.2f', $time)." secs, memory: ".sprintf('%.2f', (memory_get_peak_usage(true) / 1000000)). ' MB');
        }
    }

    /**
     * @param string[] $paths
     * @param MigrationService $migrationService
     * @return MigrationDefinition[]
     *
     * @todo this does not scale well with many definitions or migrations
     */
    protected function buildMigrationsList($paths, $migrationService)
    {
        $migrationDefinitions = $migrationService->getMigrationsDefinitions($paths);
        $migrations = $migrationService->getMigrations();

        // filter away all migrations except 'to do' ones
        $toExecute = array();
        foreach ($migrationDefinitions as $name => $migrationDefinition) {
            if (!isset($migrations[$name]) || (($migration = $migrations[$name]) && $migration->status == Migration::STATUS_TODO)) {
                $toExecute[$name] = $migrationService->parseMigrationDefinition($migrationDefinition);
            }
        }

        // if user wants to execute 'all' migrations: look for some which are registered in the database even if not
        // found by the loader
        if (empty($paths)) {
            foreach ($migrations as $migration) {
                if ($migration->status == Migration::STATUS_TODO && !isset($toExecute[$migration->name])) {
                    $migrationDefinitions = $migrationService->getMigrationsDefinitions(array($migration->path));
                    if (count($migrationDefinitions)) {
                        $migrationDefinition = reset($migrationDefinitions);
                        $toExecute[$migration->name] = $migrationService->parseMigrationDefinition($migrationDefinition);
                    } else {
                        // q: shall we raise a warning here ?
                    }
                }
            }
        }

        ksort($toExecute);

        return $toExecute;
    }

    /**
     * @param MigrationDefinition[] $toExecute
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @todo use a more compact output when there are *many* migrations
     */
    protected function printMigrationsList($toExecute, InputInterface $input, OutputInterface $output)
    {
        $data = array();
        $i = 1;
        foreach ($toExecute as $name => $migrationDefinition) {
            $notes = '';
            if ($migrationDefinition->status != MigrationDefinition::STATUS_PARSED) {
                $notes = '<error>' . $migrationDefinition->parsingError . '</error>';
            }
            $data[] = array(
                $i++,
                $name,
                $notes
            );
        }

        if (!$input->getOption('child')) {
            $table = $this->getHelperSet()->get('table');
            $table
                ->setHeaders(array('#', 'Migration', 'Notes'))
                ->setRows($data);
            $table->render($output);
        }

        $this->writeln('');
    }

    protected function askForConfirmation(InputInterface $input, OutputInterface $output)
    {
        if ($input->isInteractive() && !$input->getOption('no-interaction')) {
            $dialog = $this->getHelperSet()->get('dialog');
            if (!$dialog->askConfirmation(
                $output,
                '<question>Careful, the database will be modified. Do you want to continue Y/N ?</question>',
                false
            )
            ) {
                $output->writeln('<error>Migration execution cancelled!</error>');
                return 0;
            }
        } else {
            $this->writeln("=============================================\n");
        }

        return 1;
    }

    /**
     * Small tricks to allow us to lower verbosity between NORMAL and QUIET and have a decent writeln API, even with old SF versions
     * @param $message
     * @param int $verbosity
     */
    protected function writeln($message, $verbosity = OutputInterface::VERBOSITY_NORMAL)
    {
        if ($this->verbosity >= $verbosity) {
            $this->output->writeln($message);
        }
    }

    protected function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    protected function setVerbosity($verbosity)
    {
        $this->verbosity = $verbosity;
    }

}

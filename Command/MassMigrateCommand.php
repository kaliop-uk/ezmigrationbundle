<?php

namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Process\PhpExecutableFinder;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\Core\Helper\ProcessManager;

class MassMigrateCommand extends MigrateCommand
{
    const COMMAND_NAME = 'kaliop:migration:mass_migrate';

    protected $migrationsDone = array(0, 0, 0);
    protected $migrationsAlreadyDone = array();

    /**
     * @todo (!important) can we rename the option --separate-process ?
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName(self::COMMAND_NAME)
            ->setAliases(array())
            ->setDescription('Executes available migration definitions, using parallelism.')
            ->addOption('concurrency', 'r', InputOption::VALUE_REQUIRED, "The number of executors to run in parallel", 2)
            ->setHelp(<<<EOT
This command is designed to scan recursively a directory for migration files and execute them all in parallel.
One child process will be spawned for each subdirectory found.
The maximum number of processes to run in parallel is specified via the 'concurrency' option.
<info>NB: this command does not guarantee that any given migration will be executed before another. Take care about dependencies.</info>
<info>NB: the rule that each migration filename has to be unique still applies, even if migrations are spread across different directories.</info>
Unlike for the 'normal' migration command, it is not recommended to use the <info>--separate-process</info> option, as it will make execution slower if you have many migrations
EOT
            )
        ;
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

        $isChild = $input->getOption('child');

        if ($isChild) {
            $this->setVerbosity(self::VERBOSITY_CHILD);
        }

        $this->getContainer()->get('ez_migration_bundle.step_executed_listener.tracing')->setOutput($output);

        // q: is it worth declaring a new, dedicated migration service ?
        $migrationService = $this->getMigrationService();
        $migrationService->setLoader($this->getContainer()->get('ez_migration_bundle.loader.filesystem_recursive'));

        $force = $input->getOption('force');

        $toExecute = $this->buildMigrationsList($input->getOption('path'), $migrationService, $force, $isChild);

        if (!count($toExecute)) {
            $this->writeln('<info>No migrations to execute</info>');
            return 0;
        }

        if (!$isChild) {
            return $this->executeAsParent($input, $output, $toExecute, $start);

        } else {
            return $this->executeAsChild($input, $output, $toExecute, $force, $migrationService);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param MigrationDefinition[] $toExecute
     * @param float $start
     * @return int
     */
    protected function executeAsParent($input, $output, $toExecute, $start)
    {
        $paths = $this->groupMigrationsByPath($toExecute);
        $this->printMigrationsList($toExecute, $input, $output, $paths);

        // ask user for confirmation to make changes
        if (!$this->askForConfirmation($input, $output, null)) {
            return 0;
        }

        $concurrency = $input->getOption('concurrency');
        $this->writeln("Executing migrations using " . count($paths) . " processes with a concurrency of $concurrency");

        $builder = new ProcessBuilder();
        $executableFinder = new PhpExecutableFinder();
        if (false !== ($php = $executableFinder->find())) {
            $builder->setPrefix($php);
        }

        // mandatory args and options
        $builderArgs = $this->createChildProcessArgs($input);

        $processes = array();
        /** @var MigrationDefinition $migrationDefinition */
        foreach($paths as $path => $count) {
            $this->writeln("<info>Queueing processing of: $path ($count migrations)</info>", OutputInterface::VERBOSITY_VERBOSE);

            $process = $builder
                ->setArguments(array_merge($builderArgs, array('--path=' . $path)))
                ->getProcess();

            $this->writeln('<info>Command: ' . $process->getCommandLine() . '</info>', OutputInterface::VERBOSITY_VERBOSE);

            // allow long migrations processes by default
            $process->setTimeout($this->subProcessTimeout);
            // allow forcing handling of sigchild. Useful on eg. Debian and Ubuntu
            if ($input->getOption('force-sigchild-handling')) {
                $process->setEnhanceSigchildCompatibility(true);
            }
            $processes[] = $process;
        }

        $this->writeln("Starting queued processes...");

        $this->migrationsDone = array(0, 0, 0);

        $processManager = new ProcessManager();
        $processManager->runParallel($processes, $concurrency, 500, array($this, 'onSubProcessOutput'));

        $failed = 0;
        foreach ($processes as $i => $process) {
            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                if ($errorOutput === '') {
                    $errorOutput = "(separate process used to execute migration failed with no stderr output. Its exit code was: " . $process->getExitCode();
                    if ($process->getExitCode() == -1) {
                        $errorOutput .= ". If you are using Debian or Ubuntu linux, please consider using the --force-sigchild-handling option.";
                    }
                    $errorOutput .= ")";
                }
                /// @todo should we always add the exit code, even when $errorOutput is not null ?
                $this->errOutput->writeln("\n<error>Subprocess $i failed! Reason: " . $errorOutput . "</error>\n");
                $failed++;
            }
        }

        if ($input->getOption('clear-cache')) {
            $command = $this->getApplication()->find('cache:clear');
            $inputArray = new ArrayInput(array('command' => 'cache:clear'));
            $command->run($inputArray, $output);
        }

        $time = microtime(true) - $start;

        $this->writeln('<info>'.$this->migrationsDone[0].' migrations executed, '.$this->migrationsDone[1].($failed ? ' or more' : '').' failed, '.$this->migrationsDone[2].' skipped</info>');

        // since we use subprocesses, we can not measure max memory used
        $this->writeln("Time taken: ".sprintf('%.2f', $time)." secs");

        return $failed + $this->migrationsDone[1];
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param MigrationDefinition[] $toExecute
     * @param bool $force
     * @param $migrationService
     * @return int
     */
    protected function executeAsChild($input, $output, $toExecute, $force, $migrationService)
    {
        // @todo disable signal slots that are harmful during migrations, if any

        if ($input->getOption('separate-process')) {
            $builder = new ProcessBuilder();
            $executableFinder = new PhpExecutableFinder();
            if (false !== $php = $executableFinder->find()) {
                $builder->setPrefix($php);
            }

            $builderArgs = parent::createChildProcessArgs($input);
        }

        $forceSigChild = $input->getOption('force-sigchild-handling');

        $failed = 0;
        $executed = 0;
        $skipped = 0;
        $total = count($toExecute);

        foreach ($toExecute as  $name => $migrationDefinition) {
            // let's skip migrations that we know are invalid - user was warned and he decided to proceed anyway
            if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
                $this->writeln("<comment>Skipping migration (invalid definition?) Path: ".$migrationDefinition->path."</comment>", self::VERBOSITY_CHILD);
                $skipped++;
                continue;
            }

            if ($input->getOption('separate-process')) {

                try {
                    $this->executeMigrationInSeparateProcess($migrationDefinition, $migrationService, $builder, $builderArgs, false, $forceSigChild);

                    $executed++;
                } catch (\Exception $e) {

                    $errorMessage = preg_replace('/^\n*Migration aborted! Reason: */', '', $e->getMessage());

                    if ($input->getOption('ignore-failures')) {
                        $this->errOutput->writeln("\n<error>Migration failed! Path: " . $migrationDefinition->path . ", Reason: " . $errorMessage . "</error>\n");
                        $failed++;
                        continue;
                    }
                    $this->errOutput->writeln("\n<error>Migration aborted! Path: " . $migrationDefinition->path . ", Reason: " . $errorMessage . "</error>");

                    $missed = $total - $executed - $failed - $skipped;
                    $this->writeln("Migrations executed: $executed, failed: $failed, skipped: $skipped, to do: $missed");

                    return 1;
                }

            } else {

                try {
                    $this->executeMigrationInProcess($migrationDefinition, $force, $migrationService, $input);

                    $executed++;
                } catch(\Exception $e) {
                    $failed++;
                    if ($input->getOption('ignore-failures')) {
                        $this->writeErrorln("<error>Migration failed! Path: " . $migrationDefinition->path . ", Reason: " . $e->getMessage() . "</error>", self::VERBOSITY_CHILD);
                        continue;
                    }

                    $this->writeErrorln("<error>Migration aborted! Path: " . $migrationDefinition->path . ", Reason: " . $e->getMessage() . "</error>", self::VERBOSITY_CHILD);

                    $missed = $total - $executed - $failed - $skipped;
                    $this->writeln("Migrations executed: $executed, failed: $failed, skipped: $skipped, to do: $missed");

                    return 1;
                }

            }
        }

        $this->writeln("Migrations executed: $executed, failed: $failed, skipped: $skipped", self::VERBOSITY_CHILD);

        // We do not return an error code > 0 if migrations fail, but only on proper fatals.
        // The parent will analyze the output of the child process to gather the number of executed/failed migrations anyway
        //return $failed;
    }

    public function onSubProcessOutput($type, $buffer, $process=null)
    {
        $lines = explode("\n", trim($buffer));

        foreach ($lines as $line) {
            if (preg_match('/Migrations executed: ([0-9]+), failed: ([0-9]+), skipped: ([0-9]+)/', $line, $matches)) {
                $this->migrationsDone[0] += $matches[1];
                $this->migrationsDone[1] += $matches[2];
                $this->migrationsDone[2] += $matches[3];

                // swallow these lines unless we are in verbose mode
                if ($this->verbosity <= Output::VERBOSITY_NORMAL) {
                    return;
                }
            }

            // we tag the output from the different processes
            if (trim($line) !== '') {
                $msg = '[' . ($process ? $process->getPid() : ''). '] ' . trim($line);
                if ($type == 'err') {
                    $this->errOutput->writeln($msg, OutputInterface::OUTPUT_RAW);
                } else {
                    $this->output->writeln($msg, OutputInterface::OUTPUT_RAW);
                }
            }
        }
    }

    /**
     * @param string $paths
     * @param $migrationService
     * @param bool $force
     * @param bool $isChild when not in child mode, do not waste time parsing migrations
     * @return MigrationDefinition[] parsed or unparsed, depending on
     *
     * @todo this does not scale well with many definitions or migrations
     */
    protected function buildMigrationsList($paths, $migrationService, $force = false, $isChild = false)
    {
        $migrationDefinitions = $migrationService->getMigrationsDefinitions($paths);
        $migrations = $migrationService->getMigrations();

        $this->migrationsAlreadyDone = array(Migration::STATUS_DONE => 0, Migration::STATUS_FAILED => 0, Migration::STATUS_SKIPPED => 0, Migration::STATUS_STARTED => 0);

        $allowedStatuses = array(Migration::STATUS_TODO);
        if ($force) {
            $allowedStatuses = array_merge($allowedStatuses, array(Migration::STATUS_DONE, Migration::STATUS_FAILED, Migration::STATUS_SKIPPED));
        }

        // filter away all migrations except 'to do' ones
        $toExecute = array();
        foreach($migrationDefinitions as $name => $migrationDefinition) {
            if (!isset($migrations[$name]) || (($migration = $migrations[$name]) && in_array($migration->status, $allowedStatuses))) {
                $toExecute[$name] = $isChild ? $migrationService->parseMigrationDefinition($migrationDefinition) : $migrationDefinition;
            }
            // save the list of non-executable migrations as well (even when using 'force')
            if (!$isChild && isset($migrations[$name]) && (($migration = $migrations[$name]) && $migration->status != Migration::STATUS_TODO)) {
                $this->migrationsAlreadyDone[$migration->status]++;
            }
        }

        // if user wants to execute 'all' migrations: look for some which are registered in the database even if not
        // found by the loader
        if (empty($paths)) {
            foreach ($migrations as $migration) {
                if (in_array($migration->status, $allowedStatuses) && !isset($toExecute[$migration->name])) {
                    $migrationDefinitions = $migrationService->getMigrationsDefinitions(array($migration->path));
                    if (count($migrationDefinitions)) {
                        $migrationDefinition = reset($migrationDefinitions);
                        $toExecute[$migration->name] = $isChild ? $migrationService->parseMigrationDefinition($migrationDefinition) : $migrationDefinition;
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
     * We use a more compact output when there are *many* migrations
     * @param MigrationDefinition[] $toExecute
     * @param array $paths
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function printMigrationsList($toExecute, InputInterface $input, OutputInterface $output, $paths = array())
    {
        $output->writeln('Found ' . count($toExecute) . ' migrations in ' . count($paths) . ' directories');
        $output->writeln('In the same directories, migrations previously executed: ' . $this->migrationsAlreadyDone[Migration::STATUS_DONE] .
            ', failed: ' . $this->migrationsAlreadyDone[Migration::STATUS_FAILED] . ', skipped: '. $this->migrationsAlreadyDone[Migration::STATUS_SKIPPED]);
        if ($this->migrationsAlreadyDone[Migration::STATUS_STARTED]) {
            $output->writeln('<info>In the same directories, migrations currently executing: ' . $this->migrationsAlreadyDone[Migration::STATUS_STARTED] . '</info>');
        }
    }

    /**
     * @param MigrationDefinition[] $toExecute
     * @return array key: folder name, value: number of migrations found
     */
    protected function groupMigrationsByPath($toExecute)
    {
        $paths = array();
        foreach($toExecute as $name => $migrationDefinition) {
            $path = dirname($migrationDefinition->path);
            if (!isset($paths[$path])) {
                $paths[$path] = 1;
            } else {
                $paths[$path]++;
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * Returns the command-line arguments needed to execute a migration in a separate subprocess (omitting 'path')
     * @param InputInterface $input
     * @return array
     */
    protected function createChildProcessArgs(InputInterface $input)
    {
        $kernel = $this->getContainer()->get('kernel');

        // mandatory args and options
        $builderArgs = array(
            $_SERVER['argv'][0], // sf console
            self::COMMAND_NAME, // name of sf command. Can we get it from the Application instead of hardcoding?
            '--env=' . $kernel-> getEnvironment(), // sf env
            '--child'
        );
        // sf/ez env options
        if (!$kernel->isDebug()) {
            $builderArgs[] = '--no-debug';
        }
        if ($input->getOption('siteaccess')) {
            $builderArgs[] = '--siteaccess=' . $input->getOption('siteaccess');
        }
        // 'optional' options
        // note: options 'clear-cache', 'no-interaction', 'path' we never propagate
        if ($input->getOption('admin-login')) {
            $builderArgs[] = '--admin-login=' . $input->getOption('admin-login');
        }
        if ($input->getOption('default-language')) {
            $builderArgs[] = '--default-language=' . $input->getOption('default-language');
        }
        if ($input->getOption('force')) {
            $builderArgs[] = '--force';
        }
        if ($input->getOption('ignore-failures')) {
            $builderArgs[] = '--ignore-failures';
        }
        if ($input->getOption('no-transactions')) {
            $builderArgs[] = '--no-transactions';
        }
        if ($input->getOption('separate-process')) {
            $builderArgs[] = '--separate-process';
        }
        // useful in case the subprocess has a migration step of type process/run
        if ($input->getOption('force-sigchild-handling')) {
            $builderArgs[] = '--force-sigchild-handling';
        }

        return $builderArgs;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Command;

use Kaliop\eZMigrationBundle\API\Exception\AfterMigrationExecutionException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\Core\Helper\ProcessManager;
use Kaliop\eZMigrationBundle\Core\Process\Process;
use Kaliop\eZMigrationBundle\Core\Process\ProcessBuilder;

class MassMigrateCommand extends MigrateCommand
{
    const COMMAND_NAME = 'kaliop:migration:mass_migrate';

    // Note: in this array, we lump together in STATUS_DONE everything which is not failed or suspended
    protected $migrationsDone = array(Migration::STATUS_DONE => 0, Migration::STATUS_FAILED => 0, Migration::STATUS_SKIPPED => 0);
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

        if ($isChild && $output->getVerbosity() <= OutputInterface::VERBOSITY_NORMAL) {
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

        if ($isChild) {
            return $this->executeAsChild($input, $output, $toExecute, $force, $migrationService);
        } else {
            return $this->executeAsParent($input, $output, $toExecute, $start);
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

        // For cli scripts, this means: do not die if anyone yanks out our stdout.
        // We presume that users who want to halt migrations do send us a KILL signal, and that a lost tty is
        // generally a mistake, and that carrying on with executing migrations is the best outcome
        if ($input->getOption('survive-disconnected-tty')) {
            ignore_user_abort(true);
        }

        $concurrency = $input->getOption('concurrency');
        $this->writeln("Executing migrations using " . count($paths) . " processes with a concurrency of $concurrency");

        // allow forcing handling of sigchild. Useful on eg. Debian and Ubuntu
        if ($input->getOption('force-sigchild-enabled')) {
            Process::forceSigchildEnabled(true);
        }

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
            $processes[] = $process;
        }

        $this->writeln("<info>Starting queued processes...</info>");

        $total = count($toExecute);
        $this->migrationsDone = array(0, 0, 0);

        $processManager = new ProcessManager();
        $processManager->runParallel($processes, $concurrency, 500, array($this, 'onChildProcessOutput'));

        $subprocessesFailed = 0;
        foreach ($processes as $i => $process) {
            if (!$process->isSuccessful()) {
                $errorOutput = $process->getErrorOutput();
                if ($errorOutput === '') {
                    $errorOutput = "(process used to execute migrations failed with no stderr output. Its exit code was: " . $process->getExitCode();
                    if ($process->getExitCode() == -1) {
                        $errorOutput .= ". If you are using Debian or Ubuntu linux, please consider using the --force-sigchild-enabled option.";
                    }
                    $errorOutput .= ")";
                }
                /// @todo should we always add the exit code, even when $errorOutput is not null ?
                $this->writeErrorln("\n<error>Subprocess $i failed! Reason: " . $errorOutput . "</error>\n");
                $subprocessesFailed++;
            }
        }

        if ($input->getOption('clear-cache')) {
            /// @see the comment in the parent class about the problems tied to clearing Sf cache in-process
            $command = $this->getApplication()->find('cache:clear');
            $inputArray = new ArrayInput(array('command' => 'cache:clear'));
            $command->run($inputArray, $output);
        }

        $missed = $total - $this->migrationsDone[Migration::STATUS_DONE] - $this->migrationsDone[Migration::STATUS_FAILED] - $this->migrationsDone[Migration::STATUS_SKIPPED];
        $this->writeln("\nExecuted ".$this->migrationsDone[Migration::STATUS_DONE].' migrations'.
            ', failed '.$this->migrationsDone[Migration::STATUS_FAILED].
            ', skipped '.$this->migrationsDone[Migration::STATUS_SKIPPED].
            ($missed ? ", missed $missed" : ''));

        $time = microtime(true) - $start;
        // since we use subprocesses, we can not measure max memory used
        $this->writeln("<info>Time taken: ".sprintf('%.3f', $time)." secs</info>");

        return $subprocessesFailed + $this->migrationsDone[Migration::STATUS_FAILED] + $missed;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param MigrationDefinition[] $toExecute
     * @param bool $force
     * @param $migrationService
     * @return int
     * @todo does it make sense to honour the `survive-disconnected-tty` flag when executing as child?
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

        // allow forcing handling of sigchild. Useful on eg. Debian and Ubuntu
        if ($input->getOption('force-sigchild-enabled')) {
            Process::forceSigchildEnabled(true);
        }

        $aborted = false;
        $executed = 0;
        $failed = 0;
        $skipped = 0;
        $total = count($toExecute);

        foreach ($toExecute as  $name => $migrationDefinition) {
            // let's skip migrations that we know are invalid - user was warned and he decided to proceed anyway
            if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
                $this->writeln("<comment>Skipping migration (invalid definition?) Path: ".$migrationDefinition->path."</comment>", self::VERBOSITY_CHILD);
                $skipped++;
                continue;
            }

            $this->writeln("<info>Processing $name</info>", self::VERBOSITY_CHILD);

            if ($input->getOption('separate-process')) {

                try {
                    $this->executeMigrationInSeparateProcess($migrationDefinition, $migrationService, $builder, $builderArgs);

                    $executed++;
                } catch (\Exception $e) {
                    $failed++;

                    $errorMessage = $e->getMessage();
                    if ($errorMessage != $this->subProcessErrorString) {
                        $errorMessage = preg_replace('/^\n*(\[[0-9]*\])?(Migration failed|Failure after migration end)! Reason: +/', '', $errorMessage);
                        if ($e instanceof AfterMigrationExecutionException) {
                            $errorMessage = "Failure after migration end! Path: " . $migrationDefinition->path . ", Reason: " . $errorMessage;
                        } else {
                            $errorMessage = "Migration failed! Path: " . $migrationDefinition->path . ", Reason: " . $errorMessage;
                        }

                        $this->writeErrorln("\n<error>$errorMessage</error>");
                    }

                    if (!$input->getOption('ignore-failures')) {
                        $aborted = true;
                        break;
                    }
                }

            } else {

                try {
                    $this->executeMigrationInProcess($migrationDefinition, $force, $migrationService, $input);

                    $executed++;
                } catch(\Exception $e) {
                    $failed++;

                    $errorMessage = $e->getMessage();
                    $this->writeErrorln("\n<error>Migration failed! Path: " . $migrationDefinition->path . ", Reason: " . $errorMessage . "</error>");

                    if (!$input->getOption('ignore-failures')) {
                        $aborted = true;
                        break;
                    }
                }

            }
        }

        $missed = $total - $executed - $failed - $skipped;

        if ($aborted && $missed > 0) {
            $this->writeErrorln("\n<error>Migration execution aborted</error>");
        }

        $this->writeln("Migrations executed: $executed, failed: $failed, skipped: $skipped, missed: $missed", self::VERBOSITY_CHILD);

        // We do not return an error code > 0 if migrations fail but , but only on proper fatals.
        // The parent will analyze the output of the child process to gather the number of executed/failed migrations anyway
        return 0;
    }

    /**
     * @param string $type
     * @param string $buffer
     * @param null|\Symfony\Component\Process\Process $process
     */
    public function onChildProcessOutput($type, $buffer, $process=null)
    {
        $lines = explode("\n", trim($buffer));

        foreach ($lines as $line) {
            if (preg_match('/Migrations executed: ([0-9]+), failed: ([0-9]+), skipped: ([0-9]+)/', $line, $matches)) {
                $this->migrationsDone[Migration::STATUS_DONE] += $matches[1];
                $this->migrationsDone[Migration::STATUS_FAILED] += $matches[2];
                $this->migrationsDone[Migration::STATUS_SKIPPED] += $matches[3];

                // swallow the recap lines unless we are in verbose mode
                if ($this->verbosity <= Output::VERBOSITY_NORMAL) {
                    return;
                }
            }

            // we tag the output with the id of the child process
            if (trim($line) !== '') {
                $msg = '[' . ($process ? $process->getPid() : ''). '] ' . trim($line);
                if ($type == 'err') {
                    $this->writeErrorln($msg, OutputInterface::VERBOSITY_QUIET, OutputInterface::OUTPUT_RAW);
                } else {
                    // swallow output of child processes in quiet mode
                    $this->writeLn($msg, self::VERBOSITY_CHILD, OutputInterface::OUTPUT_RAW);
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
                        $migrationDefinition = $migrationDefinitions->reset();
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
     * Returns the command-line arguments needed to execute a separate subprocess that will run a set of migrations
     * (except path, which should be added after this call)
     * @param InputInterface $input
     * @return array
     * @todo check if it is a good idea to pass on the current verbosity
     * @todo shall we pass to child processes the `survive-disconnected-tty` flag?
     */
    protected function createChildProcessArgs(InputInterface $input)
    {
        $kernel = $this->getContainer()->get('kernel');

        // mandatory args and options
        $builderArgs = array(
            $this->getConsoleFile(), // sf console
            self::COMMAND_NAME, // name of sf command. Can we get it from the Application instead of hardcoding?
            '--env=' . $kernel->getEnvironment(), // sf env
            '--child'
        );
        // sf/ez env options
        if (!$kernel->isDebug()) {
            $builderArgs[] = '--no-debug';
        }
        if ($input->getOption('siteaccess')) {
            $builderArgs[] = '--siteaccess=' . $input->getOption('siteaccess');
        }
        switch ($this->verbosity) {
            // no propagation of 'quiet' mode, as we always need to have at least the child output with executed migs
            case OutputInterface::VERBOSITY_VERBOSE:
                $builderArgs[] = '-v';
                break;
            case OutputInterface::VERBOSITY_VERY_VERBOSE:
                $builderArgs[] = '-vv';
                break;
            case OutputInterface::VERBOSITY_DEBUG:
                $builderArgs[] = '-vvv';
                break;
        }
        // 'optional' options
        // note: options 'clear-cache', 'no-interaction', 'path' and 'survive-disconnected-tty' we never propagate
        if ($input->getOption('admin-login')) {
            $builderArgs[] = '--admin-login=' . $input->getOption('admin-login');
        }
        if ($input->getOption('default-language')) {
            $builderArgs[] = '--default-language=' . $input->getOption('default-language');
        }
        if ($input->getOption('force')) {
            $builderArgs[] = '--force';
        }
        // useful in case the subprocess has a migration step of type process/run
        if ($input->getOption('force-sigchild-enabled')) {
            $builderArgs[] = '--force-sigchild-enabled';
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
        if ($input->getOption('set-reference')) {
            foreach($input->getOption('set-reference') as $refSpec) {
                $builderArgs[] = '--set-reference=' . $refSpec;
            }
        }
        return $builderArgs;
    }
}

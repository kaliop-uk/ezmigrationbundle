<?php

namespace Kaliop\eZMigrationBundle\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\PhpExecutableFinder;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Exception\AfterMigrationExecutionException;
use Kaliop\eZMigrationBundle\Core\MigrationService;
use Kaliop\eZMigrationBundle\Core\Process\Process;
use Kaliop\eZMigrationBundle\Core\Process\ProcessBuilder;

/**
 * Command to execute the available migration definitions.
 */
class MigrateCommand extends AbstractCommand
{
    // in between QUIET and NORMAL
    const VERBOSITY_CHILD = 0.5;

    protected $subProcessTimeout = 86400;
    protected $subProcessErrorString = '';

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
            // nb: when adding options, remember to forward them to sub-commands executed in 'separate-process' mode
            ->addOption('admin-login', 'a', InputOption::VALUE_REQUIRED, "Login of admin account used whenever elevated privileges are needed (user id 14 used by default)")
            ->addOption('clear-cache', 'c', InputOption::VALUE_NONE, "Clear the cache after the command finishes")
            ->addOption('default-language', 'l', InputOption::VALUE_REQUIRED, "Default language code that will be used if no language is provided in migration steps")
            ->addOption('force', 'f', InputOption::VALUE_NONE, "Force (re)execution of migrations already DONE, SKIPPED or FAILED. Use with great care!")
            ->addOption('ignore-failures', 'i', InputOption::VALUE_NONE, "Keep executing migrations even if one fails")
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, "Do not ask any interactive question")
            ->addOption('no-transactions', 'u', InputOption::VALUE_NONE, "Do not use a repository transaction to wrap each migration. Unsafe, but needed for legacy slot handlers")
            ->addOption('path', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "The directory or file to load the migration definitions from")
            ->addOption('separate-process', 'p', InputOption::VALUE_NONE, "Use a separate php process to run each migration. Safe if your migration leak memory. A tad slower")
            ->addOption('force-sigchild-enabled', null, InputOption::VALUE_NONE, "When using a separate php process to run each migration, tell Symfony that php was compiled with --enable-sigchild option")
            ->addOption('survive-disconnected-tty', null, InputOption::VALUE_NONE, "Keep on executing migrations even if the tty where output is written to gets removed. Useful if you run the command over an unstable ssh connection")
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

        if ($input->getOption('child') && $output->getVerbosity() <= OutputInterface::VERBOSITY_NORMAL) {
            $this->setVerbosity(self::VERBOSITY_CHILD);
        }

        $this->getContainer()->get('ez_migration_bundle.step_executed_listener.tracing')->setOutput($output);

        $migrationService = $this->getMigrationService();

        $force = $input->getOption('force');

        $toExecute = $this->buildMigrationsList($input->getOption('path'), $migrationService, $force);

        if (!count($toExecute)) {
            $output->writeln('<info>No migrations to execute</info>');
            return 0;
        }

        $this->printMigrationsList($toExecute, $input, $output);

        if (!$input->getOption('child')) {
            // ask user for confirmation to make changes
            if (!$this->askForConfirmation($input, $output)) {
                return 0;
            }
        }

        if ($input->getOption('separate-process')) {
            $builder = new ProcessBuilder();
            $executableFinder = new PhpExecutableFinder();
            if (false !== $php = $executableFinder->find()) {
                $builder->setPrefix($php);
            }
            $builderArgs = $this->createChildProcessArgs($input);
        }

        // For cli scripts, this means: do not die if anyone yanks out our stdout.
        if ($input->getOption('survive-disconnected-tty')) {
            ignore_user_abort(true);
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

        /** @var MigrationDefinition $migrationDefinition */
        foreach ($toExecute as $name => $migrationDefinition) {

            // let's skip migrations that we know are invalid - user was warned and he decided to proceed anyway
            if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
                $output->writeln("<comment>Skipping $name</comment>");
                $skipped++;
                continue;
            }

            $this->writeln("<info>Processing $name</info>");

            if ($input->getOption('separate-process')) {

                try {
                    $this->executeMigrationInSeparateProcess($migrationDefinition, $migrationService, $builder, $builderArgs);

                    $executed++;
                } catch (\Exception $e) {
                    $failed++;


                    $errorMessage = $e->getMessage();
                    // we probably have already echoed the error message while the subprocess was executing, avoid repeating it
                    if ($errorMessage != $this->subProcessErrorString) {
                        if ($e instanceof AfterMigrationExecutionException) {
                            $errorMessage = "Failure after migration end! Reason: " . $errorMessage;
                        } else {
                            $errorMessage = "Migration failed! Reason: " . $errorMessage;
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
                } catch (\Exception $e) {
                    $failed++;

                    $this->writeErrorln("\n<error>Migration failed! Reason: " . $e->getMessage() . "</error>");

                    if (!$input->getOption('ignore-failures')) {
                        $aborted = true;
                        break;
                    }
                }

            }
        }

        $missed = $total - $executed - $failed - $skipped;

        if ($aborted) {
            if ($missed > 0) {
                $this->writeErrorln("\n<error>Migration execution aborted</error>");
            }
        } else {
            // NB: as per the Sf doc at https://symfony.com/doc/2.7/console/calling_commands.html, the 'cache:clear'
            // command should be run 'at the end', as they change some class definitions
            if ($input->getOption('clear-cache')) {
                $command = $this->getApplication()->find('cache:clear');
                $inputArray = new ArrayInput(array('command' => 'cache:clear'));
                $command->run($inputArray, $output);
            }
        }

        $this->writeln("\nExecuted $executed migrations, failed $failed, skipped $skipped" . ($missed ? ", missed $missed" : ''));

        $time = microtime(true) - $start;
        if ($input->getOption('separate-process')) {
            // in case of using subprocesses, we can not measure max memory used
            $this->writeln("<info>Time taken: ".sprintf('%.2f', $time)." secs</info>");
        } else {
            $this->writeln("<info>Time taken: ".sprintf('%.2f', $time)." secs, memory: ".sprintf('%.2f', (memory_get_peak_usage(true) / 1000000)). ' MB</info>');
        }

        return $failed;
    }

    /**
     * @param MigrationDefinition $migrationDefinition
     * @param bool $force
     * @param MigrationService $migrationService
     * @param InputInterface $input
     */
    protected function executeMigrationInProcess($migrationDefinition, $force, $migrationService, $input)
    {
        $migrationService->executeMigration(
            $migrationDefinition,
            !$input->getOption('no-transactions'),
            $input->getOption('default-language'),
            $input->getOption('admin-login'),
            $force
        );
    }

    /**
     * @param MigrationDefinition $migrationDefinition
     * @param MigrationService $migrationService
     * @param ProcessBuilder $builder
     * @param array $builderArgs
     * @param bool $feedback
     */
    protected function executeMigrationInSeparateProcess($migrationDefinition, $migrationService, $builder, $builderArgs, $feedback = true)
    {
        $process = $builder
            ->setArguments(array_merge($builderArgs, array('--path=' . $migrationDefinition->path)))
            ->getProcess();

        if ($feedback) {
            $this->writeln('<info>Executing: ' . $process->getCommandLine() . '</info>', OutputInterface::VERBOSITY_VERBOSE);
        }

        $this->subProcessErrorString = '';

        // allow long migrations processes by default
        $process->setTimeout($this->subProcessTimeout);

        // and give immediate feedback to the user...
        // NB: if the subprocess writes to stderr then terminates with non-0 exit code, this will lead us to echoing the
        // error text twice, once here and once at the end of execution of this command.
        // In order to avoid that, since we can not know at this time what the subprocess exit code will be, we
        // do print the error text now, and compare it to what we gt at the end...
        $process->run(
            $feedback ?
                function($type, $buffer) {
                    if ($type == 'err') {
                        $this->subProcessErrorString .= $buffer;
                        $this->writeErrorln($buffer, OutputInterface::VERBOSITY_QUIET, OutputInterface::OUTPUT_RAW);
                    } else {
                        // swallow output of child processes in quiet mode
                        $this->writeLn($buffer, self::VERBOSITY_CHILD, OutputInterface::OUTPUT_RAW);
                    }
                }
                :
                function($type, $buffer) {
                }
        );

        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput();
            /// @todo should we always add the exit code to the error message, even when $errorOutput is not null ?
            if ($errorOutput === '') {
                $errorOutput = "(separate process used to execute migration failed with no stderr output. Its exit code was: " . $process->getExitCode();
                if ($process->getExitCode() == -1) {
                    $errorOutput .= ". If you are using Debian or Ubuntu linux, please consider using the --force-sigchild-enabled option.";
                }
                $errorOutput .= ")";
            }
            throw new \Exception($errorOutput);
        }

        // There are cases where the separate process dies halfway but does not return a non-zero code.
        // That's why we double-check here if the migration is still tagged as 'started'...
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
    }

    /**
     * @param string[] $paths
     * @param MigrationService $migrationService
     * @param bool $force when true, look not only for TODO migrations, but also DONE, SKIPPED, FAILED ones (we still omit STARTED and SUSPENDED ones)
     * @return MigrationDefinition[]
     *
     * @todo this does not scale well with many definitions or migrations
     */
    protected function buildMigrationsList($paths, $migrationService, $force = false)
    {
        $migrationDefinitions = $migrationService->getMigrationsDefinitions($paths);
        $migrations = $migrationService->getMigrations();

        $allowedStatuses = array(Migration::STATUS_TODO);
        if ($force) {
            $allowedStatuses = array_merge($allowedStatuses, array(Migration::STATUS_DONE, Migration::STATUS_FAILED, Migration::STATUS_SKIPPED));
        }

        // filter away all migrations except 'to do' ones
        $toExecute = array();
        foreach ($migrationDefinitions as $name => $migrationDefinition) {
            if (!isset($migrations[$name]) || (($migration = $migrations[$name]) && in_array($migration->status, $allowedStatuses))) {
                $toExecute[$name] = $migrationService->parseMigrationDefinition($migrationDefinition);
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
            $table = new Table($output);
            $table
                ->setHeaders(array('#', 'Migration', 'Notes'))
                ->setRows($data);
            $table->render();
        }

        $this->writeln('');
    }

    protected function askForConfirmation(InputInterface $input, OutputInterface $output, $nonIteractiveOutput = "=============================================\n")
    {
        if ($input->isInteractive() && !$input->getOption('no-interaction')) {
            $dialog = $this->getHelperSet()->get('question');
            if (!$dialog->ask(
                $input,
                $output,
                new ConfirmationQuestion('<question>Careful, the database will be modified. Do you want to continue Y/N ?</question>', false)
            )
            ) {
                $output->writeln('<error>Migration execution cancelled!</error>');
                return 0;
            }
        } else {
            if ($nonIteractiveOutput != '') {
                $this->writeln("$nonIteractiveOutput");
            }
        }

        return 1;
    }

    /**
     * Returns the command-line arguments needed to execute a migration in a separate subprocess
     * (except path, which should be added after this call)
     * @param InputInterface $input
     * @return array
     * @todo check if it is a good idea to pass on the current verbosity
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
            $builderArgs[] = '--siteaccess='.$input->getOption('siteaccess');
        }
        switch ($this->verbosity) {
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
        // note: options 'clear-cache', 'ignore-failures', 'no-interaction', 'path', 'separate-process' and 'survive-disconnected-tty' we never propagate
        if ($input->getOption('admin-login')) {
            $builderArgs[] = '--admin-login=' . $input->getOption('admin-login');
        }
        if ($input->getOption('default-language')) {
            $builderArgs[] = '--default-language=' . $input->getOption('default-language');
        }
        if ($input->getOption('force')) {
            $builderArgs[] = '--force';
        }
        if ($input->getOption('no-transactions')) {
            $builderArgs[] = '--no-transactions';
        }
        // useful in case the subprocess has a migration step of type process/run
        if ($input->getOption('force-sigchild-enabled')) {
            $builderArgs[] = '--force-sigchild-enabled';
        }

        return $builderArgs;
    }

    /**
     * Returns the file-path of the symfony console in use, based on simple heuristics
     * @return string
     * @todo improve how we look for the console: we could fe. scan all of the files in the kernel dir, or look up the full process info based on its pid
     */
    protected function getConsoleFile()
    {
        if (strpos($_SERVER['argv'][0], 'phpunit') !== false) {
            $kernelDir = $this->getContainer()->get('kernel')->getRootDir();
            if (is_file("$kernelDir/console")) {
                return "$kernelDir/console";
            }
            if (is_file("$kernelDir/../bin/console")) {
                return "$kernelDir/../bin/console";
            }
            throw new \Exception("Can not determine the name of the symfony console file in use for running as separate process");
        }

        return $_SERVER['argv'][0]; // sf console
    }
}

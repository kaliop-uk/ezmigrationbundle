<?php

namespace Kaliop\eZMigrationBundle\Command;

use Kaliop\eZMigrationBundle\API\Value\Migration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
/**
 * Command to resume suspended migrations.
 *
 * @todo add support for resuming a set based on path
 * @todo add support for the separate-process cli switch
 */
class ResumeCommand extends AbstractCommand
{
    /**
     * Set up the command.
     *
     * Define the name, options and help text.
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('kaliop:migration:resume')
            ->setDescription('Restarts any suspended migrations.')
            ->addOption('ignore-failures', 'i', InputOption::VALUE_NONE, "Keep resuming migrations even if one fails")
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, "Do not ask any interactive question.")
            ->addOption('no-transactions', 'u', InputOption::VALUE_NONE, "Do not use a repository transaction to wrap each migration. Unsafe, but needed for legacy slot handlers")
            ->addOption('migration', 'm', InputOption::VALUE_REQUIRED, 'A single migration to resume (plain migration name).', null)
            ->addOption('set-reference', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "Inject references into the migrations. Format: --set-reference refname:value --set-reference ref2name:value2")
            ->setHelp(<<<EOT
The <info>kaliop:migration:resume</info> command allows you to resume any suspended migration
EOT
            );
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null|int null or 0 if everything went fine, or an error code
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);

        $this->setOutput($output);
        $this->setVerbosity($output->getVerbosity());

        $this->getContainer()->get('ez_migration_bundle.step_executed_listener.tracing')->setOutput($output);

        $migrationService = $this->getMigrationService();
        $migrationService->setOutput($output);

        $migrationName = $input->getOption('migration');
        if ($migrationName != null) {
            $suspendedMigration = $migrationService->getMigration($migrationName);
            if (!$suspendedMigration) {
                throw new \Exception("Migration '$migrationName' not found");
            }
            if ($suspendedMigration->status != Migration::STATUS_SUSPENDED) {
                throw new \Exception("Migration '$migrationName' is not suspended, can not resume it");
            }

            $suspendedMigrations = array($suspendedMigration);
        } else {
            $suspendedMigrations = $migrationService->getMigrationsByStatus(Migration::STATUS_SUSPENDED);
        };

        $output->writeln('<info>Found ' . count($suspendedMigrations) . ' suspended migrations</info>');

        if (!count($suspendedMigrations)) {
            $output->writeln('Nothing to do');
            return 0;
        }

        // ask user for confirmation to make changes
        if ($input->isInteractive() && !$input->getOption('no-interaction')) {
            $dialog = $this->getHelperSet()->get('question');
            if (!$dialog->ask(
                $input,
                $output,
                new ConfirmationQuestion('<question>Careful, the database will be modified. Do you want to continue Y/N ?</question>', false)
            )
            ) {
                $output->writeln('<error>Migration resuming cancelled!</error>');
                return 0;
            }
        }

        $forcedRefs = array();
        if ($input->getOption('set-reference') /*&& !$input->getOption('separate-process')*/) {
            $refResolver = $this->getContainer()->get('ez_migration_bundle.reference_resolver.customreference');
            foreach($input->getOption('set-reference') as $refSpec) {
                $ref = explode(':', $refSpec, 2);
                if (count($ref) < 2 || $ref[0] === '') {
                    throw new \Exception("Invalid reference specification: '$refSpec'");
                }
                $forcedRefs[$ref[0]] = $ref[1];
            }
        }

        $executed = 0;
        $failed = 0;

        foreach($suspendedMigrations as $suspendedMigration) {
            $output->writeln("<info>Resuming {$suspendedMigration->name}</info>");

            try {
                $migrationService->resumeMigration($suspendedMigration, !$input->getOption('no-transactions'), $forcedRefs);

                $executed++;
            } catch (\Exception $e) {
                if ($input->getOption('ignore-failures')) {
                    $this->errOutput->writeln("\n<error>Migration failed! Reason: " . $e->getMessage() . "</error>\n");
                    $failed++;
                    continue;
                }
                $this->errOutput->writeln("\n<error>Migration aborted! Reason: " . $e->getMessage() . "</error>");
                return 1;
            }
        }

        $time = microtime(true) - $start;
        $output->writeln("Resumed $executed migrations, failed $failed");
        $output->writeln("Time taken: ".sprintf('%.3f', $time)." secs, memory: ".sprintf('%.2f', (memory_get_peak_usage(true) / 1000000)). ' MB');

        if ($failed) {
            return 2;
        }

        return 0;
    }
}

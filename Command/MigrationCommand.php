<?php

namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Command to execute the available migration definitions.
 */
class MigrationCommand extends AbstractCommand
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
            ->setName('kaliop:migration:migration')
            ->setDescription('Manually modify or get info about migrations in the database table.')
            ->addOption('delete', null, InputOption::VALUE_NONE, "Delete the specified migration.")
            ->addOption('info', null, InputOption::VALUE_NONE, "Get info about the specified migration.")
            ->addOption('add', null, InputOption::VALUE_NONE, "Add the specified migration definition.")
            ->addOption('skip', null, InputOption::VALUE_NONE, "Mark the specified migration as skipped.")
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, "Do not ask any interactive question.")
            ->addArgument('migration', InputArgument::REQUIRED, 'The migration to add/skip (filename with full path) or detail/delete (plain migration name).', null)
            ->setHelp(<<<EOT
The <info>kaliop:migration:migration</info> command allows you to manually delete migrations versions from the migration table:

    <info>./ezpublish/console kaliop:migration:migration --delete migration_name</info>

As well as manually adding migrations to the migration table:

    <info>./ezpublish/console kaliop:migration:migration --add /path/to/migration_definition</info>
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
        $this->setOutput($output);
        $this->setVerbosity($output->getVerbosity());

        if (!$input->getOption('add') && !$input->getOption('delete') && !$input->getOption('skip') && !$input->getOption('info')) {
            throw new \InvalidArgumentException('You must specify whether you want to --add, --delete, --skip or --info the specified migration.');
        }

        $migrationService = $this->getMigrationService();
        $migrationNameOrPath = $input->getArgument('migration');

        if ($input->getOption('info')) {
            $output->writeln('');

            $migration = $migrationService->getMigration($migrationNameOrPath);
            if ($migration == null) {
                throw new \InvalidArgumentException(sprintf('The migration "%s" does not exist in the migrations table.', $migrationNameOrPath));
            }

            switch ($migration->status) {
                case Migration::STATUS_DONE:
                    $status = '<info>executed</info>';
                    break;
                case Migration::STATUS_STARTED:
                    $status = '<comment>execution started</comment>';
                    break;
                case Migration::STATUS_TODO:
                    // bold to-migrate!
                    $status = '<error>not executed</error>';
                    break;
                case Migration::STATUS_SKIPPED:
                    $status = '<comment>skipped</comment>';
                    break;
                case Migration::STATUS_PARTIALLY_DONE:
                    $status = '<comment>partially executed</comment>';
                    break;
                case Migration::STATUS_SUSPENDED:
                    $status = '<comment>suspended</comment>';
                    break;
                case Migration::STATUS_FAILED:
                    $status = '<error>failed</error>';
                    break;
            }

            $output->writeln('<info>Migration: ' . $migration->name . '</info>');
            $output->writeln('Status: ' . $status);
            $output->writeln('Executed on: <info>' . ($migration->executionDate != null ? date("Y-m-d H:i:s", $migration->executionDate) : '--'). '</info>');
            $output->writeln('Execution notes: <info>' . $migration->executionError . '</info>');

            if ($migration->status == Migration::STATUS_SUSPENDED) {
                /// @todo decode the suspension context: date, step, ...
            }

            $output->writeln('Definition path: <info>' . $migration->path . '</info>');
            $output->writeln('Definition md5: <info>' . $migration->md5 . '</info>');

            if ($migration->path != '') {
                // q: what if we have a loader which does not work with is_file? We could probably remove this check...
                if (is_file($migration->path)) {
                    try {
                        $migrationDefinitionCollection = $migrationService->getMigrationsDefinitions(array($migration->path));
                        if (count($migrationDefinitionCollection)) {
                            $migrationDefinition = $migrationDefinitionCollection->reset();
                            $migrationDefinition = $migrationService->parseMigrationDefinition($migrationDefinition);

                            if ($migrationDefinition->status != MigrationDefinition::STATUS_PARSED) {
                                $output->writeln('Definition error: <error>' . $migrationDefinition->parsingError . '</error>');
                            }

                            if (md5($migrationDefinition->rawDefinition) != $migration->md5) {
                                $output->writeln('Notes: <comment>The migration definition file has now a different checksum</comment>');
                            }
                        } else {
                            $output->writeln('Definition error: <error>The migration definition file can not be loaded</error>');
                        }
                    } catch (\Exception $e) {
                        /// @todo one day we should be able to limit the kind of exceptions we have to catch here...
                        $output->writeln('Definition parsing error: <error>' . $e->getMessage() . '</error>');
                    }
                } else {
                    $output->writeln('Definition error: <error>The migration definition file can not be found any more</error>');
                }
            }

            $output->writeln('');
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
                $output->writeln('<error>Migration change cancelled!</error>');
                return 0;
            }
        }

        if ($input->getOption('add')) {
            // will throw if a file is passed and it is not found, but not if an empty dir is passed
            $migrationDefinitionCollection = $migrationService->getMigrationsDefinitions(array($migrationNameOrPath));

            if (!count($migrationDefinitionCollection))
            {
                throw new \InvalidArgumentException(sprintf('The path "%s" does not correspond to any migration definition.', $migrationNameOrPath));
            }

            foreach ($migrationDefinitionCollection as $migrationDefinition) {
                $migrationName = basename($migrationDefinition->path);

                $migration = $migrationService->getMigration($migrationNameOrPath);
                if ($migration != null) {
                    throw new \InvalidArgumentException(sprintf('The migration "%s" does already exist in the migrations table.', $migrationName));
                }

                $migrationService->addMigration($migrationDefinition);
                $output->writeln('<info>Added migration' . $migrationDefinition->path . '</info>');
            }

            return 0;
        }

        if ($input->getOption('delete')) {
            $migration = $migrationService->getMigration($migrationNameOrPath);
            if ($migration == null) {
                throw new \InvalidArgumentException(sprintf('The migration "%s" does not exist in the migrations table.', $migrationNameOrPath));
            }

            $migrationService->deleteMigration($migration);

            return 0;
        }

        if ($input->getOption('skip')) {
            // will throw if a file is passed and it is not found, but not if an empty dir is passed
            $migrationDefinitionCollection = $migrationService->getMigrationsDefinitions(array($migrationNameOrPath));

            if (!count($migrationDefinitionCollection))
            {
                throw new \InvalidArgumentException(sprintf('The path "%s" does not correspond to any migration definition.', $migrationNameOrPath));
            }

            foreach ($migrationDefinitionCollection as $migrationDefinition) {
                $migrationService->skipMigration($migrationDefinition);
                $output->writeln('<info>Migration' . $migrationDefinition->path . ' marked as skipped</info>');
            }

            return 0;
        }

        throw new \InvalidArgumentException("Please specify one action to be taken on the given migration");
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

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
            ->setDescription('Manually delete migrations from the database table.')
            ->addOption('delete', null, InputOption::VALUE_NONE, "Delete the specified migration.")
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, "Do not ask any interactive question.")
            ->addArgument('migration', InputArgument::REQUIRED, 'The version to add or delete.', null)
            ->setHelp(
                <<<EOT
The <info>kaliop:migration:migration</info> command allows you to manually delete migrations versions from the migration table:

    <info>./ezpublish/console kaliop:migration:migration migration_name --delete</info>
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
        if (/*! $input->getOption('add') &&*/ ! $input->getOption('delete')) {
            throw new \InvalidArgumentException('You must specify whether you want to --delete the specified migration.');
        }

        $migrationsService = $this->getMigrationService();
        $migrationName = $input->getArgument('migration');

        // ask user for confirmation to make changes
        if ($input->isInteractive() && !$input->getOption('no-interaction')) {
            $dialog = $this->getHelperSet()->get('dialog');
            if (!$dialog->askConfirmation(
                $output,
                '<question>Careful, the database will be modified. Do you want to continue Y/N ?</question>',
                false
            )
            ) {
                $output->writeln('<error>Migration change cancelled!</error>');
                return 0;
            }
        }

        if ($input->getOption('delete')) {
            $migration = $migrationsService->getMigration($migrationName);
            if ($migration == null) {
                throw new \InvalidArgumentException(sprintf('The migration "%s" does not exist in the migrations table.', $migrationName));
            }

            $migrationsService->deleteMigration($migration);
        }
    }
}

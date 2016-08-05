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
            ->addOption('add', null, InputOption::VALUE_NONE, "Add the specified migration definition.")
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, "Do not ask any interactive question.")
            ->addArgument('migration', InputArgument::REQUIRED, 'The version to add (filename with full path) or delete (plain migration name).', null)
            ->setHelp(
                <<<EOT
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
        if (! $input->getOption('add') && ! $input->getOption('delete')) {
            throw new \InvalidArgumentException('You must specify whether you want to --add or --delete the specified migration.');
        }

        $migrationService = $this->getMigrationService();
        $migrationNameOrPath = $input->getArgument('migration');

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

        if ($input->getOption('add')) {
            // will throw if a file is passed and it is not found, but not if an empty dir is passed
            $migrationDefinitionCollection = $migrationService->getMigrationsDefinitions(array($migrationNameOrPath));

            if (!count($migrationDefinitionCollection))
            {
                throw new \InvalidArgumentException(sprintf('The path "%s" does not correspond to any migration definition.', $migrationNameOrPath));
            }

            foreach($migrationDefinitionCollection as $migrationDefinition) {
                $migrationName = basename($migrationDefinition->path);

                $migration = $migrationService->getMigration($migrationNameOrPath);
                if ($migration != null) {
                    throw new \InvalidArgumentException(sprintf('The migration "%s" does already exist in the migrations table.', $migrationName));
                }

                $migrationService->addMigration($migrationDefinition);
                $output->writeln('<info>Added migration' . $migrationDefinition->path . '</info>');
            }

            return;
        }

        if ($input->getOption('delete')) {
            $migration = $migrationService->getMigration($migrationNameOrPath);
            if ($migration == null) {
                throw new \InvalidArgumentException(sprintf('The migration "%s" does not exist in the migrations table.', $migrationNameOrPath));
            }

            $migrationService->deleteMigration($migration);
        }
    }
}

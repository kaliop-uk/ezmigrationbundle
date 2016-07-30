<?php

namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\Table;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;

/**
 * Command to execute the available migration definitions.
 */
class UpdateCommand extends AbstractCommand
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
            ->setName('kaliop:migration:update')
            ->setDescription('Execute available migration definitions.')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                "The directory or file to load the migration definitions from"
            )
            ->addOption('clear-cache', null, InputOption::VALUE_NONE, "Clear the cache after the command finishes")
            ->setHelp(
                <<<EOT
                The <info>kaliop:migration:update</info> command loads and execute migrations for your bundles:

<info>./ezpublish/console kaliop:migration:update</info>

You can optionally specify the path to migration definitions with <info>--path</info>:

<info>./ezpublish/console kaliop:migrations:update --path=/path/to/bundle/version_directory --path=/path/to/bundle/version_directory/single_migration_file</info>
EOT
            );
    }

    /**
     * Execute the command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return type
     *
     * @todo Add functionality to work with specified version files not just directories.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $migrationsService = $this->getMigrationService();

        $migrationDefinitions = $migrationsService->getMigrationsDefinitions($input->getOption('path')) ;
        $migrations = $migrationsService->getMigrations();

        if (!count($migrationDefinitions)) {
            $output->writeln('<info>No migrations found</info>');
            return;
        }

        // filter away all migrations except 'to do' ones
        $toExecute = array();
        foreach($migrationDefinitions as $name => $migrationDefinition) {
            if (!isset($migrations[$name]) || ($migration = $migrations[$name] && $migration->status == Migration::STATUS_TODO)) {
                $toExecute[$name] = $migrationsService->parseMigrationDefinition($migrationDefinition);
            }
        }
        // just in case...
        ksort($toExecute);

        if (!count($toExecute)) {
            $output->writeln('<info>No migrations to execute</info>');
            return;
        }

        $output->writeln("\n <info>==</info> Migrations to be executed\n");

        $data = array();
        $i = 1;
        foreach($toExecute as $name => $migrationDefinition) {
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

        $table = new Table($output);
        $table
            ->setHeaders(array('#', 'Migration', 'Notes'))
            ->setRows($data);
        $table->render();

        $output->writeln('');
        // ask for user confirmation to make changes
        if ($input->isInteractive()) {
            $dialog = $this->getHelperSet()->get('dialog');
            if (!$dialog->askConfirmation(
                $output,
                '<question>Careful, the database will be modified. Do you want to continue Y/N ?</question>',
                false
            )
            ) {
                $output->writeln('<error>Migration cancelled!</error>');
                return 1;
            }
        } else {
            $output->writeln("=============================================\n");
        }

        foreach($toExecute as $name => $migrationDefinition) {

            // let's skip migrations that we know are invalid - user was warned and he decide to proceed anyway
            if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
                $output->writeln("<warning>Skipping $name</warning>\n");
                continue;
            }

            $output->writeln("<info>Processing $name</info>\n");

            try {
                $migrationsService->executeMigration($migrationDefinition);
            } catch(\Exception $e) {
                $output->writeln("\n<error>Migration aborted!".$e->getMessage()."</error>");
                return 1;
            }

            $output->writeln('');
        }

        if ($input->getOption('clear-cache')) {
            $command = $this->getApplication()->find('cache:clear');
            $inputArray = new ArrayInput(array('command' => 'cache:clear'));
            $command->run($inputArray, $output);
        }
    }
}

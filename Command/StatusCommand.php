<?php

namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;

/**
 * Command to display the status of migrations.
 *
 * @todo add option to skip displaying already executed migrations
 */
class StatusCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('kaliop:migration:status')
            ->setDescription('View the status of a set of migrations.')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                "The directory or file to load the migration definitions from"
            )
            ->setHelp(<<<EOT
The <info>kaliop:migration:status</info> command displays the status of all available migrations:

    <info>./ezpublish/console kaliop:migration:status</info>

You can optionally specify the path to migration versions with <info>--path</info>:

    <info>./ezpublish/console kaliop:migrations:status --path=/path/to/bundle/version_directory --path=/path/to/bundle/version_directory/single_migration_file</info>
EOT
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $migrationsService = $this->getMigrationService();

        $migrationDefinitions = $migrationsService->getMigrationsDefinitions($input->getOption('path')) ;
        $migrations = $migrationsService->getMigrations();

        if (!count($migrationDefinitions) && !count($migrations)) {
            $output->writeln('<info>No migrations found</info>');
            return;
        }

        // create a unique ist of all migrations (coming from db) and definitions (coming from disk)
        $index = array();
        foreach($migrationDefinitions as $migrationDefinition) {
            $index[$migrationDefinition->name] = array('definition' => $migrationDefinition);
        }
        foreach($migrations as $migration) {
            if (isset($index[$migration->name])) {
                $index[$migration->name]['migration'] = $migration;
            } else {
                $index[$migration->name] = array('migration' => $migration);

                // no definition, but a migration is there. Check if the definition sits elsewhere on disk than we expect it to be...
                if ($migration->path != '' && is_file($migration->path)) {
                    try {
                        $migrationDefinitionCollection = $migrationsService->getMigrationsDefinitions(array($migration->path));
                        if (count($migrationDefinitionCollection))
                        {
                            $index[$migration->name]['definition'] = reset($migrationDefinitionCollection);
                        }
                    } catch(\Exception $e) {
                        /// @todo one day we should be able to limit the kind of exceptions we have to catch here...
                    }
                }
            }
        }
        ksort($index);

        $output->writeln("\n <info>==</info> All Migrations\n");

        $data = array();
        $i = 1;
        foreach($index as $name => $value) {
            if (!isset($value['migration'])) {
                $migrationDefinition = $migrationsService->parseMigrationDefinition($value['definition']);
                $notes = '';
                if ($migrationDefinition->status != MigrationDefinition::STATUS_PARSED) {
                    $notes = '<error>' . $migrationDefinition->parsingError . '</error>';
                }
                $data[] = array(
                    $i++,
                    $name,
                    '<error>not executed</error>',
                    '',
                    $notes
                );
            } else {
                $migration = $value['migration'];
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
                    case Migration::STATUS_FAILED:
                        $status = '<error>failed</error>';
                        break;
                }
                $notes = array();
                if ($migration->executionError != '') {
                    $notes[] = "<error>{$migration->executionError}</error>";
                }
                if (!isset($value['definition'])) {
                    $notes[] = '<comment>The migration definition file can not be found any more</comment>';
                } else {
                    $migrationDefinition = $value['definition'];
                    if (md5($migrationDefinition->rawDefinition) != $migration->md5) {
                        $notes[] = '<comment>The migration definition file has now a different checksum</comment>';
                    }
                    if ($migrationDefinition->path != $migrationDefinition->path) {
                        $notes[] = '<comment>The migration definition file has now moved</comment>';
                    }
                }
                $notes = implode(' ', $notes);
                $data[] = array(
                    $i++,
                    $migration->name,
                    $status,
                    ($migration->executionDate != null ? date("Y-m-d H:i:s", $migration->executionDate) : ''),
                    $notes
                );
            }
        }

        $table = $this->getHelperSet()->get('table');
        $table
            ->setHeaders(array('#', 'Migration', 'Status', 'Executed on', 'Notes'))
            ->setRows($data);
        $table->render($output);
    }
}

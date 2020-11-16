<?php

namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Symfony\Component\Console\Helper\Table;

/**
 * Command to display the status of migrations.
 *
 * @todo add option to skip displaying already executed migrations
 * @todo allow sorting migrations by their execution date
 */
class StatusCommand extends AbstractCommand
{
    const STATUS_INVALID = -1;

    protected function configure()
    {
        $this->setName('kaliop:migration:status')
            ->setDescription('View the status of all (or a set of) migrations.')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "The directory or file to load the migration definitions from")
            ->addOption('sort-by', null, InputOption::VALUE_REQUIRED, "Supported sorting order: name, execution", 'name')
            ->addOption('summary', null, InputOption::VALUE_NONE, "Only print summary information")
            ->addOption('todo', null, InputOption::VALUE_NONE, "Only print list of migrations to execute (full path to each)")
            ->addOption('show-path', null, InputOption::VALUE_NONE, "Print migration path instead of status")
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
        $this->setOutput($output);
        $this->setVerbosity($output->getVerbosity());

        $migrationsService = $this->getMigrationService();

        $displayPath = $input->getOption('show-path');
        $migrationDefinitions = $migrationsService->getMigrationsDefinitions($input->getOption('path'));
        $migrations = $migrationsService->getMigrationsByPaths($input->getOption('path'));

        if (!count($migrationDefinitions) && !count($migrations)) {
            $output->writeln('<info>No migrations found</info>');
            return 0;
        }

        // create a unique list of all migrations (coming from db) and definitions (coming from disk)
        $index = array();
        foreach ($migrationDefinitions as $migrationDefinition) {
            $index[$migrationDefinition->name] = array('definition' => $migrationDefinition);
        }
        foreach ($migrations as $migration) {
            if (isset($index[$migration->name])) {
                $index[$migration->name]['migration'] = $migration;
            } else {
                $index[$migration->name] = array('migration' => $migration);

                // no definition, but a migration is there. Check if the definition sits elsewhere on disk than we expect it to be...
                // q: what if we have a loader which does not work with is_file? Could we remove this check?
                if ($migration->path != '' && is_file($migration->path)) {
                    try {
                        $migrationDefinitionCollection = $migrationsService->getMigrationsDefinitions(array($migration->path));
                        if (count($migrationDefinitionCollection)) {
                            $index[$migration->name]['definition'] = $migrationDefinitionCollection->reset();
                        }
                    } catch (\Exception $e) {
                        /// @todo one day we should be able to limit the kind of exceptions we have to catch here...
                    }
                }
            }
        }

        if (!$input->getOption('summary')) {
            $this->sortMigrationIndex($index, $input->getOption('sort-by'));
        }

        if (!$input->getOption('summary') && !$input->getOption('todo')) {
            if (count($index) > 50000) {
                $output->writeln("WARNING: printing the status table might take a while as it contains many rows. Please wait...");
            }
            $output->writeln("\n <info>==</info> All Migrations\n");
        }

        $summary = array(
            self::STATUS_INVALID => array('Invalid', 0),
            Migration::STATUS_TODO => array('To do', 0),
            Migration::STATUS_STARTED => array('Started', 0),
            Migration::STATUS_DONE => array('Done', 0),
            Migration::STATUS_SUSPENDED => array('Suspended', 0),
            Migration::STATUS_FAILED => array('Failed', 0),
            Migration::STATUS_SKIPPED => array('Skipped', 0),
            Migration::STATUS_PARTIALLY_DONE => array('Partially done', 0),
        );
        $data = array();

        $i = 1;
        foreach ($index as $name => $value) {
            if (!isset($value['migration'])) {
                $migrationDefinition = $migrationsService->parseMigrationDefinition($value['definition']);
                $notes = $displayPath ? $migrationDefinition->path : '';
                if ($migrationDefinition->status != MigrationDefinition::STATUS_PARSED) {
                    if (!$displayPath) {
                        $notes = '<error>' . $migrationDefinition->parsingError . '</error>';
                    }
                    $summary[self::STATUS_INVALID][1]++;
                } else {
                    $summary[Migration::STATUS_TODO][1]++;
                }
                if ($input->getOption('todo')) {
                    $data[] = $migrationDefinition->path;
                } else {
                    $data[] = array(
                        $i++,
                        $name,
                        '<error>not executed</error>',
                        '',
                        $notes
                    );

                }
            } else {
                /** @var Migration $migration */
                $migration = $value['migration'];

                if (!isset($summary[$migration->status])) {
                    $summary[$migration->status] = array($migration->status, 0);
                }
                $summary[$migration->status][1]++;
                if ($input->getOption('summary')) {
                    continue;
                }

                if ($input->getOption('todo')) {
                    if ($migration->status == Migration::STATUS_TODO) {
                        $data[] = $migration->path;
                    }

                    continue;
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
                if ($displayPath) {
                    $notes = $migration->path;
                } else {
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
                }

                $data[] = array(
                    $i++,
                    $migration->name,
                    $status,
                    ($migration->executionDate != null ? date("Y-m-d H:i:s", $migration->executionDate) : ''),
                    $notes
                );
            }
        }

        if ($input->getOption('todo')) {
            foreach ($data as $migrationData) {
                $output->writeln("$migrationData", OutputInterface::OUTPUT_RAW|OutputInterface::VERBOSITY_QUIET);
            }
            return 0;
        }

        if ($input->getOption('summary')) {
            $output->writeln("\n <info>==</info> Migrations Summary\n");
            // do not print info about the not yet supported case
            unset($summary[Migration::STATUS_PARTIALLY_DONE]);
            $data = $summary;
            $headers = array('Status', 'Count');
        } else {
            $headers = array('#', 'Migration', 'Status', 'Executed on', $displayPath ? 'Path' : 'Notes');
        }

        $table = new Table($output);
        $table
            ->setHeaders($headers)
            ->setRows($data);
        $table->render();
    }

    /**
     * @param array[] $index
     * @param string $sortBy
     * @throws \Exception
     */
    protected function sortMigrationIndex(array &$index, $sortBy)
    {
        switch($sortBy) {
            case 'execution':
                uasort($index, function($m1, $m2) {
                    if (isset($m1['migration']) && $m1['migration']->executionDate !== null) {
                        if (isset($m2['migration']) && $m2['migration']->executionDate !== null) {
                            return $m1['migration']->executionDate - $m2['migration']->executionDate;
                        } else {
                            // m2 not executed: send to bottom
                            return -1;
                        }
                    } else {
                        if (isset($m2['migration']) && $m2['migration']->executionDate !== null) {
                            // m1 not executed: send to bottom
                            return 1;
                        } else {
                            // both not executed: compare by name
                            return strcmp(
                                isset($m1['migration']) ? $m1['migration']->name : $m1['definition']->name,
                                isset($m2['migration']) ? $m2['migration']->name : $m2['definition']->name
                            );
                        }
                    }
                });
                break;
            case 'name':
                ksort($index);
                break;
            default:
                throw new \Exception("Unsupported sort order: '$sortBy'");
        }
    }
}

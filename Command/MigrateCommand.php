<?php

namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use eZ\Publish\API\Repository\Exceptions\ContentTypeFieldDefinitionValidationException;

/**
 * Command to execute the available migration definitions.
 */
class MigrateCommand extends AbstractCommand
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
            ->setName('kaliop:migration:migrate')
            ->setAliases(array('kaliop:migration:update'))
            ->setDescription('Execute available migration definitions.')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                "The directory or file to load the migration definitions from"
            )
            ->addOption('ignore-failures', null, InputOption::VALUE_NONE, "Keep executing migration even if one fails")
            ->addOption('clear-cache', null, InputOption::VALUE_NONE, "Clear the cache after the command finishes")
            ->addOption('no-interaction', 'n', InputOption::VALUE_NONE, "Do not ask any interactive question")
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
            if (!isset($migrations[$name]) || (($migration = $migrations[$name]) && $migration->status == Migration::STATUS_TODO)) {
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

        $table = $this->getHelperSet()->get('table');
        $table
            ->setHeaders(array('#', 'Migration', 'Notes'))
            ->setRows($data);
        $table->render($output);

        $output->writeln('');
        // ask user for confirmation to make changes
        if ($input->isInteractive() && !$input->getOption('no-interaction')) {
            $dialog = $this->getHelperSet()->get('dialog');
            if (!$dialog->askConfirmation(
                $output,
                '<question>Careful, the database will be modified. Do you want to continue Y/N ?</question>',
                false
            )
            ) {
                $output->writeln('<error>Migration execution cancelled!</error>');
                return 0;
            }
        } else {
            $output->writeln("=============================================\n");
        }

        foreach($toExecute as $name => $migrationDefinition) {

            // let's skip migrations that we know are invalid - user was warned and he decide to proceed anyway
            if ($migrationDefinition->status == MigrationDefinition::STATUS_INVALID) {
                $output->writeln("<comment>Skipping $name</comment>\n");
                continue;
            }

            $output->writeln("<info>Processing $name</info>");

            try {
                $migrationsService->executeMigration($migrationDefinition);
            } catch(\Exception $e) {
                if ($input->getOption('ignore-failures')) {
                    $output->writeln("\n<error>Migration failed! Reason: ".$this->getFullExceptionMessage($e)."</error>\n");
                    continue;
                }
                $output->writeln("\n<error>Migration aborted! Reason: ".$this->getFullExceptionMessage($e)."</error>");
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

    /**
     * Turns eZPublish cryptic exceptions into something more palatable for random devs
     * @todo should this be moved to a lower layer ?
     *
     * @param \Exception $e
     * @return string
     */
    protected function getFullExceptionMessage(\Exception $e)
    {
        $message = $e->getMessage();
        if (is_a($e, '\eZ\Publish\API\Repository\Exceptions\ContentTypeFieldDefinitionValidationException') ||
            is_a($e, '\eZ\Publish\API\Repository\Exceptions\ContentTypeFieldValidationException') ||
            is_a($e, '\eZ\Publish\API\Repository\Exceptions\LimitationValidationException')
        ) {
            if (is_a($e, '\eZ\Publish\API\Repository\Exceptions\LimitationValidationException')) {
                $errorsArray = array($e->getValidationErrors());
            } else {
                $errorsArray = $e->getFieldErrors();
            }

            foreach ($errorsArray as $errors) {
                foreach ($errors as $error) {
                    /// @todo find out what is the proper eZ way of getting a translated message for these errors
                    $translatableMessage = $error->getTranslatableMessage();
                    if (is_a($e, 'eZ\Publish\API\Repository\Values\Translation\Plural')) {
                        $msgText = $translatableMessage->plural;
                    } else {
                        $msgText = $translatableMessage->message;
                    }

                    $message .= "\n" . $msgText . " - " . var_export($translatableMessage->values, true);
                }
            }
        }
        return $message;
    }
}

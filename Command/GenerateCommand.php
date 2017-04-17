<?php

namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Yaml\Yaml;
use Kaliop\eZMigrationBundle\API\MigrationGeneratorInterface;
use Kaliop\eZMigrationBundle\API\MatcherInterface;
use Kaliop\eZMigrationBundle\API\LanguageAwareInterface;

class GenerateCommand extends AbstractCommand
{
    const DIR_CREATE_PERMISSIONS = 0755;

    private $availableMigrationFormats = array('yml', 'php', 'sql', 'json');
    private $availableModes = array('create', 'update', 'delete');
    private $availableTypes = array('role', 'content', 'content_type', 'content_type_group', 'object_state_group', 'section', 'generic', 'db', 'php');
    private $thisBundle = 'EzMigrationBundle';

    /**
     * Configure the console command
     */
    protected function configure()
    {
        $this->setName('kaliop:migration:generate')
            ->setDescription('Generate a blank migration definition file.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The format of migration file to generate (' . implode(', ', $this->availableMigrationFormats) . ')', 'yml')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'The type of migration to generate (' . implode(', ', $this->availableTypes) . ')', '')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'The mode of the migration (' . implode(', ', $this->availableModes) . ')', 'create')
            ->addOption('match-type', null, InputOption::VALUE_REQUIRED, 'The type of identifier used to find the entity to generate the migration for', null)
            ->addOption('match-value', null, InputOption::VALUE_REQUIRED, 'The identifier value used to find the entity to generate the migration for. Can have many values separated by commas', null)
            ->addOption('match-except', null, InputOption::VALUE_NONE, 'Used to match all entities except the ones satisfying the match-value condition', null)
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'The language of the migration (eng-GB, ger-DE, ...)', 'eng-GB')
            ->addOption('dbserver', null, InputOption::VALUE_REQUIRED, 'The type of the database server the sql migration is for, when type=db (mysql, postgresql, ...)', 'mysql')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'Deprecated: The role identifier (or id) that you would like to update, for type=role', null)
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle to generate the migration definition file in. eg.: AcmeMigrationBundle')
            ->addArgument('name', InputArgument::OPTIONAL, 'The migration name (will be prefixed with current date)', null)
            ->setHelp(<<<EOT
The <info>kaliop:migration:generate</info> command generates a skeleton migration definition file:

    <info>php ezpublish/console kaliop:migration:generate bundlename</info>

You can optionally specify the file type to generate with <info>--format</info>, as well a name for the migration:

    <info>php ezpublish/console kaliop:migration:generate --format=json bundlename migrationname</info>

For SQL type migration you can optionally specify the database server type the migration is for with <info>--dbserver</info>:

    <info>php ezpublish/console kaliop:migration:generate --format=sql bundlename</info>

For role/content/content_type migrations you need to specify the entity that you want to generate the migration for:

    <info>php ezpublish/console kaliop:migration:generate --type=content --match-type=content_id --match-value=10,14 bundlename</info>

For role type migration you will receive a yaml file with the current role definition. You must define ALL the policies
you wish for the role. Any not defined will be removed.

    <info>php ezpublish/console kaliop:migration:generate --type=role --match-value=Anonymous bundlename</info>

For freeform php migrations, you will receive a php class definition

    <info>php ezpublish/console kaliop:migration:generate --format=php bundlename classname</info>

Note that you can pass in a custom directory path instead of a bundle name, but, if you do, you will have to use the <info>--path</info>
option when you run the <info>migrate</info> command.
EOT
            );
    }

    /**
     * Run the command and display the results.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null|int null or 0 if everything went fine, or an error code
     * @throws \InvalidArgumentException When an unsupported file type is selected
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $bundleName = $input->getArgument('bundle');
        $name = $input->getArgument('name');
        $fileType = $input->getOption('format');
        $migrationType = $input->getOption('type');
        $role = $input->getOption('role');
        $matchType = $input->getOption('match-type');
        $matchValue = $input->getOption('match-value');
        $matchExcept = $input->getOption('match-except');
        $mode = $input->getOption('mode');
        $dbServer = $input->getOption('dbserver');

        if ($role != '') {
            $output->writeln('<error>The "role" option is deprecated since version 3.2 and will be removed in 4.0. Use "type=role", "match-type=identifier" and "match-value" instead.</error>');
            $migrationType = 'role';
            $matchType = 'identifier';
            $matchValue = $role;
            if ($mode == '') {
                $mode = 'update';
            }
        }

        if ($bundleName == $this->thisBundle) {
            throw new \InvalidArgumentException("It is not allowed to create migrations in bundle '$bundleName'");
        }

        // be kind to lazy users
        if ($migrationType == '') {
            if ($fileType == 'sql') {
                $migrationType = 'db';
            } elseif ($fileType == 'php') {
                $migrationType = 'php';
            } else {
                $migrationType = 'generic';
            }
        }

        if (!in_array($fileType, $this->availableMigrationFormats)) {
            throw new \InvalidArgumentException('Unsupported migration file format ' . $fileType);
        }

        if (!in_array($mode, $this->availableModes)) {
            throw new \InvalidArgumentException('Unsupported migration mode ' . $mode);
        }

        $migrationDirectory = $this->getMigrationDirectory($bundleName);

        if (!is_dir($migrationDirectory)) {
            $output->writeln(sprintf(
                "Migrations directory <info>%s</info> does not exist. I will create it now....",
                $migrationDirectory
            ));

            if (mkdir($migrationDirectory, self::DIR_CREATE_PERMISSIONS, true)) {
                $output->writeln(sprintf(
                    "Migrations directory <info>%s</info> has been created",
                    $migrationDirectory
                ));
            } else {
                throw new FileException(sprintf(
                    "Failed to create migrations directory %s.",
                    $migrationDirectory
                ));
            }
        }

        // allow to generate migrations for many entities
        if (strpos($matchValue, ',') !== false ) {
            $matchValue = explode(',', $matchValue);
        }

        $parameters = array(
            'dbserver' => $dbServer,
            'matchType' => $matchType,
            'matchValue' => $matchValue,
            'matchExcept' => $matchExcept,
            'mode' => $mode,
            'lang' => $input->getOption('lang')
        );

        $date = date('YmdHis');

        switch ($fileType) {
            case 'sql':
                /// @todo this logic should come from the DefinitionParser, really
                if ($name != '') {
                    $name = '_' . ltrim($name, '_');
                }
                $fileName = $date . '_' . $dbServer . $name . '.sql';
                break;

            case 'php':
                /// @todo this logic should come from the DefinitionParser, really
                $className = ltrim($name, '_');
                if ($className == '') {
                    $className = 'Migration';
                }
                // Make sure that php class names are unique, not only migration definition file names
                $existingMigrations = count(glob($migrationDirectory . '/*_' . $className . '*.php'));
                if ($existingMigrations) {
                    $className = $className . sprintf('%03d', $existingMigrations + 1);
                }
                $parameters = array_merge($parameters, array(
                    'class_name' => $className
                ));
                $fileName = $date . '_' . $className . '.php';
                break;

            default:
                if ($name == '') {
                    $name = 'placeholder';
                }
                $fileName = $date . '_' . $name . '.' . $fileType;
        }

        $path = $migrationDirectory . '/' . $fileName;

        $warning = $this->generateMigrationFile($path, $fileType, $migrationType, $parameters);

        $output->writeln(sprintf("Generated new migration file: <info>%s</info>", $path));

        if ($warning != '') {
            $output->writeln("<comment>$warning</comment>");
        }
    }

    /**
     * Generates a migration definition file.
     *
     * @param string $path filename to file to generate (full path)
     * @param string $fileType The type of migration file to generate
     * @param string $migrationType The type of migration to generate
     * @param array $parameters passed on to twig
     * @return string A warning message in case file generation was OK but there was something weird
     * @throws \Exception
     */
    protected function generateMigrationFile($path, $fileType, $migrationType, array $parameters = array())
    {
        $warning = '';

        switch ($migrationType) {
            case 'db':
            case 'generic':
            case 'php':
                // Generate migration file by template
                $template = $migrationType . 'Migration.' . $fileType . '.twig';
                $templatePath = $this->getApplication()->getKernel()->getBundle($this->thisBundle)->getPath() . '/Resources/views/MigrationTemplate/';
                if (!is_file($templatePath . $template)) {
                    throw new \Exception("The combination of migration type '$migrationType' is not supported with format '$fileType'");
                }

                $code = $this->getContainer()->get('twig')->render($this->thisBundle . ':MigrationTemplate:' . $template, $parameters);
                break;

            default:
                // Generate migration file by executor
                $executors = $this->getGeneratingExecutors();
                if (!in_array($migrationType, $executors)) {
                    throw new \Exception("It is not possible to generate a migration of type '$migrationType': executor not found or not a generator");
                }
                $executor = $this->getMigrationService()->getExecutor($migrationType);

                /*if ($executor instanceof LanguageAwareInterface) {
                    $executor->setLanguageCode($parameters['lang']);
                }*/
                $context = array();
                if (isset($parameters['lang']) && $parameters['lang'] != '') {
                    $context['defaultLanguageCode'] = $parameters['lang'];
                }

                $matchCondition = array($parameters['matchType'] => $parameters['matchValue']);
                if ($parameters['matchExcept']) {
                    $matchCondition = array(MatcherInterface::MATCH_NOT => $matchCondition);
                }
                $data = $executor->generateMigration($matchCondition, $parameters['mode'], $context);

                if (!is_array($data) || !count($data)) {
                    $warning = 'Note: the generated migration is empty';
                }

                switch ($fileType) {
                    case 'yml':
                        $code = Yaml::dump($data, 5);
                        break;
                    case 'json':
                        $code = json_encode($data, JSON_PRETTY_PRINT);
                        break;
                    default:
                        throw new \Exception("The combination of migration type '$migrationType' is not supported with format '$fileType'");
                }
        }

        file_put_contents($path, $code);

        return $warning;
    }

    /**
     * @param string $bundleName a bundle name or filesystem path to a directory
     * @return string
     */
    protected function getMigrationDirectory($bundleName)
    {
        // Allow direct usage of a directory path instead of a bundle name
        if (strpos($bundleName, '/') !== false && is_dir($bundleName)) {
            return rtrim($bundleName, '/');
        }

        $activeBundles = array();
        foreach ($this->getApplication()->getKernel()->getBundles() as $bundle) {
            $activeBundles[] = $bundle->getName();
        }
        asort($activeBundles);
        if (!in_array($bundleName, $activeBundles)) {
            throw new \InvalidArgumentException("Bundle '$bundleName' does not exist or it is not enabled. Try with one of:\n" . implode(', ', $activeBundles));
        }

        $bundle = $this->getApplication()->getKernel()->getBundle($bundleName);
        $migrationDirectory = $bundle->getPath() . '/' . $this->getContainer()->getParameter('kaliop_bundle_migration.version_directory');

        return $migrationDirectory;
    }

    /// @todo move somewhere else. Maybe to the MigrationService itself ?
    protected function getGeneratingExecutors()
    {
        $migrationService = $this->getMigrationService();
        $executors = $migrationService->listExecutors();
        foreach($executors as $key => $name) {
            $executor = $migrationService->getExecutor($name);
            if (!$executor instanceof MigrationGeneratorInterface) {
                unset($executors[$key]);
            }
        }
        return $executors;
    }
}

<?php

namespace Kaliop\eZMigrationBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Yaml\Yaml;
use eZ\Publish\API\Repository\Values\User\Limitation;
use Kaliop\eZMigrationBundle\Core\Helper\RoleHandler;

class GenerateCommand extends AbstractCommand
{
    const ADMIN_USER_ID = 14;
    const DIR_CREATE_PERMISSIONS = 0755;

    private $availableMigrationFormats = array('yml', 'php', 'sql');
    private $availableMigrationTypes = array('generic', 'role', 'db', 'php');
    private $thisBundle = 'EzMigrationBundle';

    /**
     * Configure the console command
     */
    protected function configure()
    {
        $this->setName('kaliop:migration:generate')
            ->setDescription('Generate a blank migration definition file.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'The format of migration file to generate (yml, php, sql)', 'yml')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'The type of migration to generate (role, generic, db, php)', '')
            ->addOption('dbserver', null, InputOption::VALUE_REQUIRED, 'The type of the database server the sql migration is for, for type=db (mysql, postgresql, ...)', 'mysql')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'The role identifier you would like to update, for type=role', null)
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle to generate the migration definition file in. eg.: AcmeMigrationBundle')
            ->addArgument('name', InputArgument::OPTIONAL, 'The migration name (will be prefixed with current date)', null)
            ->setHelp(<<<EOT
The <info>kaliop:migration:generate</info> command generates a skeleton migration definition file:

    <info>./ezpublish/console kaliop:migration:generate bundlename</info>

You can optionally specify the file type to generate with <info>--format</info>:

    <info>./ezpublish/console kaliop:migration:generate --format=yml bundlename migrationname</info>

For SQL type migration you can optionally specify the database server type the migration is for with <info>--dbserver</info>:

    <info>./ezpublish/console kaliop:migration:generate --format=sql bundlename migrationname</info>

For role type migration you will receive a yaml file with the current role definition. You must define ALL the policies you wish for the role. Any not defined will be removed.

    <info>./ezpublish/console kaliop:migration:generate --role=Anonymous bundlename migrationname

For freeform php migrations, you will receive a php class definition

    <info>./ezpublish/console kaliop:migration:generate --format=php bundlename classname</info>

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
        $dbServer = $input->getOption('dbserver');

        if ($bundleName == $this->thisBundle) {
            throw new \InvalidArgumentException("It is not allowed to create migrations in bundle '$bundleName'");
        }

        $activeBundles = array();
        foreach($this->getApplication()->getKernel()->getBundles() as $bundle)
        {
            $activeBundles[] = $bundle->getName();
        }
        asort($activeBundles);
        if (!in_array($bundleName, $activeBundles)) {
            throw new \InvalidArgumentException("Bundle '$bundleName' does not exist or it is not enabled. Try with one of:\n" . implode(', ', $activeBundles));
        }

        $bundle = $this->getApplication()->getKernel()->getBundle($bundleName);
        $migrationDirectory = $bundle->getPath() . '/' . $this->getContainer()->getParameter('kaliop_bundle_migration.version_directory');

        // be kind to lazy users
        if ($migrationType == '') {
            if ($fileType == 'sql') {
                $migrationType = 'db';
            } elseif ($fileType == 'php') {
                $migrationType = 'php';
            } elseif ($role != '') {
                $migrationType = 'role';
            } else {
                $migrationType = 'generic';
            }
        }

        if (!in_array($fileType, $this->availableMigrationFormats)) {
            throw new \InvalidArgumentException('Unsupported migration file format ' . $fileType);
        }

        if (!in_array($migrationType, $this->availableMigrationTypes)) {
            throw new \InvalidArgumentException('Unsupported migration type ' . $fileType);
        }

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

        $parameters = array(
            'dbserver' => $dbServer,
            'role' => $role,
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
                $existingMigrations = count(glob($migrationDirectory.'/*_' . $className .'*.php'));
                if ($existingMigrations) {
                    $className = $className . sprintf('%03d', $existingMigrations + 1);
                }
                $parameters = array_merge($parameters, array(
                    'namespace' => $bundle->getNamespace(),
                    'class_name' => $className
                ));
                $fileName = $date . '_' . $className . '.php';
                break;

            default:
                if ($name == '') {
                    $name = 'placeholder';
                }
                $fileName = $date . '_' . $name . '.yml';
        }

        $path = $migrationDirectory . '/' . $fileName;

        $this->generateMigrationFile($path, $fileType, $migrationType, $parameters);

        $output->writeln(sprintf("Generated new migration file: <info>%s</info>", $path));
    }

    /**
     * Generates a migration definition file.
     *
     * @param string $path filename to file to generate (full path)
     * @param string $fileType The type of migration file to generate
     * @param string $migrationType The type of migration to generate
     * @param array $parameters passed on to twig
     * @return string The path to the migration file
     * @throws \Exception
     */
    protected function generateMigrationFile($path, $fileType, $migrationType, array $parameters = array())
    {
        $template = $migrationType . 'Migration.' . $fileType . '.twig';
        $templatePath = $this->getApplication()->getKernel()->getBundle($this->thisBundle)->getPath() . '/Resources/views/MigrationTemplate/';
        if (!is_file($templatePath . $template) && $migrationType != 'role') {
            throw new \Exception("The combination of migration type '$migrationType' is not supported with format '$fileType'");
        }

        if ($migrationType == 'role') {
            $code = $this->generateRoleTemplate($parameters['role']);
        } else {
            $code = $this->getContainer()->get('twig')->render($this->thisBundle . ':MigrationTemplate:'.$template, $parameters);
        }

        file_put_contents($path, $code);
    }

    /**
     * @todo to be moved into the Executor/RoleManager class
     *
     * @param string$roleName
     * @return string
     */
    protected function generateRoleTemplate($roleName)
    {
        /** @var $container \Symfony\Component\DependencyInjection\ContainerInterface */
        $container = $this->getApplication()->getKernel()->getContainer();
        $repository = $container->get('ezpublish.api.repository');
        $repository->setCurrentUser($repository->getUserService()->loadUser(self::ADMIN_USER_ID));

        /** @var \eZ\Publish\Core\SignalSlot\RoleService $roleService */
        $roleService = $repository->getRoleService();

        /** @var RoleHandler $roleTranslationHandler */
        $roleTranslationHandler = $container->get('ez_migration_bundle.helper.role_handler');

        /** @var \eZ\Publish\API\Repository\Values\User\Role $role */
        $role = $roleService->loadRoleByIdentifier($roleName);

        $policies = array();
        /** @var \eZ\Publish\API\Repository\Values\User\Policy $policy */
        foreach ($role->getPolicies() as $policy)
        {
            $limitations = array();

            /** @var \eZ\Publish\API\Repository\Values\User\Limitation $limitation */
            foreach ($policy->getLimitations() as $limitation)
            {
                $limitations[] = $roleTranslationHandler->limitationWithIdentifiers($limitation);
            }

            $policies[] = array(
                'module' => $policy->module,
                'function' => $policy->function,
                'limitations' => $limitations
            );
        }

        $ymlArray = array(
            'mode' => 'update',
            'type' => 'role',
            'name' => $roleName,
            'policies' => $policies
        );

        return Yaml::dump(array($ymlArray), 5);
    }
}

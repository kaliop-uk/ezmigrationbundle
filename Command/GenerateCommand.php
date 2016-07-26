<?php

namespace Kaliop\eZMigrationBundle\Command;

use Kaliop\eZMigrationBundle\Core\Configuration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Yaml\Yaml;
use eZ\Publish\API\Repository\Values\User\Limitation;
use Kaliop\eZMigrationBundle\Core\API\Handler\RoleTranslationHandler;

class GenerateCommand extends AbstractCommand
{
    const ADMIN_USER_ID = 14;
    const DIR_CREATE_PERMISSIONS = 0755;
    private $phpTemplate = '<?php

namespace <namespace>;

use Kaliop\eZMigrationBundle\Interfaces\VersionInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auto-generated Migration definition: Please modify to your needs!
 */
class <version>_place_holder implements VersionInterface, ContainerAwareInterface
{
    /**
     * The dependency injection container
     * @var ContainerInterface
     */
    private $container;

    /**
     * @inheritdoc
     */
    public function execute() {
        // @TODO This method is auto generated, please modify to your needs.
    }

    /**
     * @inheritdoc
     */
    public function setContainer(ContainerInterface $container = null) {
        $this->container = $container;
    }
}
';

    private $ymlTemplate = '
# Auto-generated Migration definition: Please modify to your needs!
-
    mode: [create/update/delete]
    type: [content/content_type/user/user_group/role]
    ';

    private $availableMigrationTypes = array('yml', 'php', 'sql');

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * Configure the console command
     */
    protected function configure()
    {
        $this->setName('kaliop:migration:generate')
            ->setDescription('Generate a blank migration definition file.')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'The type of migration file to generate. (yml, php, sql)', 'yml')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'The name you would like for the migration file', null)
            ->addOption('dbserver', null, InputOption::VALUE_OPTIONAL, 'The type of the database server the sql migration is for. (mysql, postgre)', 'mysql')
            ->addOption('role', null, InputOption::VALUE_OPTIONAL, 'The role identifier you would like to update.', null)
            ->addArgument('bundle', InputOption::VALUE_REQUIRED, 'The bundle to generate the migration definition file in. eg.: AcmeMigrationBundle')
            ->setHelp(<<<EOT
The <info>kaliop:migration:generate</info> command generates a skeleton migration definition file:

<info>./ezpublish/console kaliop:migration:generate bundlename</info>

You can optionally specify the file type and file name to generate with <info>--type</info> or <info>--name</info>:

<info>./ezpublish/console kaliop:migration:generate --type=yml --name=logical_file_name bundlename</info>

For SQL type migration you can optionally specify the database server type the migration is for with <info>--dbserver</info>:

<info>./ezpublish/console kaliop:migration:generate --type=sql --dbserver=mysql bundlename</info>

For role type migration you will receive a yaml file with the current role definition. You must define ALL the policies you wish for the role. Any not defined will be removed.

<info>./ezpublish/consol kaliop:migration:generate --role=Anonymous bundlename
EOT
            );
    }

    /**
     * Run the command and display the results.
     *
     * @throws \InvalidArgumentException When an unsupported file type is selected
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $fileType = $input->getOption('type');
        $fileName = $input->getOption('name');
        $dbServer = $input->getOption('dbserver');
        $this->output = $output;

        if (!in_array($fileType, $this->availableMigrationTypes)) {
            throw new \InvalidArgumentException('Unsupported migration file type ' . $fileType);
        }

        $configuration = $this->getConfiguration($input, $output);

        $version = date('YmdHis');
        $bundleName = $input->getArgument('bundle');

        $role = $input->getOption('role');

        $path = $this->generateMigrationFile($configuration, $version, $bundleName, $fileType, $fileName, $dbServer, $role);

        $output->writeln(sprintf("Generated new migration file to <info>%s</info>", $path));
    }

    /**
     * Generates a migration definition file.
     *
     * @throws \InvalidArgumentException When the destination directory does not exists
     * @param Configuration $configuration
     * @param string $version The version string in YYYYMMDDHHMMSS format
     * @param string $bundleName The name of the bundle to generate the migration file for
     * @param string $fileType The type of migration file to generate
     * @param string $fileName The name of migration file to generate
     * @param string $dbServer The type of database server the SQL migration is for.
     * @return string The path to the migration file
     */
    protected function generateMigrationFile(
        Configuration $configuration, $version, $bundleName, $fileType, $fileName = null, $dbServer = 'mysql', $role = null
    )
    {

        /** @var $bundle \Symfony\Component\HttpKernel\Bundle\BundleInterface */
        $bundle = $this->getApplication()->getKernel()->getBundle($bundleName);

        $container = $this->getApplication()->getKernel()->getContainer();

        $versionDirectory = $container->getParameter('kaliop_bundle_migration.version_directory');
        $bundleVersionDirectory = $bundle->getPath() . '/' . $versionDirectory;

        if (!is_null($fileName)) {
            $path = $bundleVersionDirectory . '/' . $version . '_' . $fileName . '.' . $fileType;
        } elseif (!is_null($role)) {
            $path = $bundleVersionDirectory . '/' . $version . '_' . $role . '_role_sync' . '.yml';
            $fileType = 'yml';
        } elseif ($fileType == 'sql') {
            $path = $bundleVersionDirectory . '/' . $version . '_' . $dbServer . '_place_holder.' . $fileType;
        } else {
            $path = $bundleVersionDirectory . '/' . $version . '_place_holder.' . $fileType;
        }

        if (!is_null($role)) {
            $template = $this->generateRoleTemplate($role);
        } else {
            switch ($fileType) {
                case 'php':
                    $template = $this->phpTemplate;
                    break;
                case 'sql':
                    $template = "-- Autogenerated migration file. Please customise for your needs.";
                    break;
                case 'yml':
                default:
                    $template = $this->ymlTemplate;
            }
        }

        $placeholders = array(
            '<namespace>',
            '<version>'
        );

        $replacements = array(
            $configuration->versionDirectory . "\\" . $bundleName,
            $version
        );

        $code = str_replace($placeholders, $replacements, $template);

        if (!file_exists($bundleVersionDirectory)) {
            $this->output->writeln(sprintf(
                "Migrations directory <info>%s</info> does not exist. I will create one now....",
                $bundleVersionDirectory
            ));

            if (mkdir($bundleVersionDirectory, self::DIR_CREATE_PERMISSIONS, true)) {
                $this->output->writeln(sprintf(
                    "Migrations directory <info>%s</info> has been created",
                    $bundleVersionDirectory
                ));
            } else {
                throw new FileException(sprintf(
                    "Failed to create migrations directory %s.",
                    $bundleVersionDirectory
                ));
            }
        }

        file_put_contents($path, $code);

        return $path;
    }

    protected function generateRoleTemplate($roleName)
    {
        /** @var $container \Symfony\Component\DependencyInjection\ContainerInterface */
        $container = $this->getApplication()->getKernel()->getContainer();
        $repository = $container->get('ezpublish.api.repository');
        $repository->setCurrentUser($repository->getUserService()->loadUser(self::ADMIN_USER_ID));

        /** @var \eZ\Publish\Core\SignalSlot\RoleService $roleService */
        $roleService = $repository->getRoleService();

        /** @var RoleTranslationHandler $roleTranslationHandler */
        $roleTranslationHandler = $container->get('ez_migration_bundle.handler.role');

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

    protected function getLimitationValues(Limitation $limitation)
    {
        /** @var $container \Symfony\Component\DependencyInjection\ContainerInterface */
        $container = $this->getApplication()->getKernel()->getContainer();
        $repository = $container->get('ezpublish.api.repository');

        /** @var \eZ\Publish\API\Repository\SectionService $sectionService */
        $sectionService = $repository->getSectionService();

        foreach( $limitation->limitationValues as $value)
        {

        }
    }
}
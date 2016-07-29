<?php

namespace Kaliop\eZMigrationBundle\Command;

use Kaliop\eZMigrationBundle\Command\AbstractCommand;
use Kaliop\eZMigrationBundle\Core\ReferenceHandler;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

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
            ->setDescription('Apply available migration definitions.')
            ->addOption(
                'versions',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                "The directory or file to load the migration instructions from"
            )
            ->addOption('clear-cache', null, InputOption::VALUE_NONE, "Clear the cache after the command finishes")
            ->setHelp(
                <<<EOT
                The <info>kaliop:migration:update</info> command loads and execute migrations for your bundles:

<info>./ezpublish/console kaliop:migration:update</info>

You can optionally specify the path to migration versions with the <info>--versions</info>:

<info>./ezpublish/console kaliop:migrations:update --versions=/path/to/bundle/version directory name/version1 --versions=/path/to/bundle/version directory name/version2</info>
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

        // Get paths to look for version files in
        $versions = $input->getOption('versions');

        /** @var $configuration \Kaliop\eZMigrationBundle\Core\Configuration */
        $configuration = $this->getConfiguration($input, $output);

        if ($versions) {
            $paths = is_array($versions) ? $versions : array($versions);
            $paths = $this->groupPathsByBundle($paths);
        } else {
            $paths = $this->groupPathsByBundle($this->getBundlePaths());
        }

        //Check paths for version files
        //Check each version/bundle pair against db to see if it can be executed
        //Print versions to be executed grouped by bundle
        $configuration->registerVersionFromDirectories($paths);

        /** @var $container \Symfony\Component\DependencyInjection\ContainerInterface */
        $container = $this->getApplication()->getKernel()->getContainer();
        $configuration->injectContainerIntoMigrations($container);
        $configuration->injectBundleIntoMigration($this->getApplication()->getKernel());

        $versionsToExecute = $configuration->migrationsToExecute();

        if ($versionsToExecute) {
            $output->writeln("\n <info>==</info> Migration Versions to be executed\n");
            foreach ($versionsToExecute as $bundle => $versions) {
                $output->writeln("<info>{$bundle}</info>:");

                if ($versions) {
                    foreach ($versions as $versionNumber => $versionClass) {
                        /** @var $versionClass \Kaliop\eZMigrationBundle\Core\Version */
                        $output->writeln(
                            "<comment>></comment> " . date(
                                "Y-m-d H:i:s",
                                strtotime($versionNumber)
                            ) . " (<comment>{$versionNumber}</comment>, <info>" . $versionClass->type . "</info>)"
                        );
                    }
                }
            }

            $output->writeln('');
            //Ask for user confirmation to make changes
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

            /** @var $repository \eZ\Publish\API\Repository\Repository */
            $repository = $container->get('ezpublish.api.repository');
            $repository->beginTransaction();

            //Execute each version to make updates
            foreach ($versionsToExecute as $bundle => $versions) {
                $output->writeln("<info>Processing updates for {$bundle}</info>\n");
                foreach ($versions as $versionNumber => $versionClass) {
                    try {
                        $versionClass->execute();
                        $configuration->markVersionMigrated($bundle, $versionNumber);
                    } catch (\Exception $e) {
                        $repository->rollBack();

                        $output->writeln("\n<error>Migration aborted!</error>");
                        return 1;
                    }
                }
                $output->writeln('');
            }
            $repository->commit();

            //Clear the whole cache
            $clearCache = $input->getOption('clear-cache');

            if ($clearCache) {
                $command = $this->getApplication()->find('cache:clear');

                $arguments = array(
                    'command' => 'cache:clear'
                );

                $inputArray = new ArrayInput($arguments);

                $command->run($inputArray, $output);
            }
        } else {
            $output->writeln('<info>No new versions found. Exiting...</info>');
        }


        // Everything went well
        return 0;

    }
}

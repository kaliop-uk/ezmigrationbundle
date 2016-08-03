<?php

use Kaliop\eZMigrationBundle\API\MigrationInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use \eZ\Publish\Core\Persistence\Database\SelectQuery;

class MigrateV1ToV2 implements MigrationInterface
{
    private $container;
    private $dbHandler;
    private $activeBundles;
    private $legacyTableName;
    private $legacyMigrationsDir;

    // The API says we have to have a static method, but we like better non-static... :-P
    public static function execute(ContainerInterface $container)
    {
        $migration = new self($container);
        $migration->goForIt();
    }

    private function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    private function goForIt()
    {
        $this->legacyTableName = $this->container->getParameter('kaliop_bundle_migration.table_name');
        $this->legacyMigrationsDir = $this->container->getParameter('kaliop_bundle_migration.version_directory');

        $migrationStorageService = $this->container->get('ez_migration_bundle.storage_handler');
        $this->dbHandler = $this->container->get('ezpublish.connection');

        $this->activeBundles = array();
        foreach($this->container->get('kernel')->getBundles() as $bundle)
        {
            $this->activeBundles[$bundle->getName()] = $bundle->getPath();
        }

        /** @var \Kaliop\eZMigrationBundle\Core\Helper\ConsoleIO $io */
        $io = $this->container->get('ez_migration_bundle.helper.console_io');
        // NB: in theory this could be null!
        $output = $io->getOutput();

        if (!$this->tableExist($this->legacyTableName))
        {
            $output->writeln("Nothing to update: v1 database table '{$this->legacyTableName}' not found");
            return;
        }

        $toMigrate = $this->loadLegacyMigrations();
        $output->writeln("<info>Found " . count($toMigrate) . ' migration versions in the v1 database table</info>');

        // we need to decide of a random time to stamp existing migrations. We use 'now - 1 minute'...
        $executionDate = time() - 60;
        foreach($toMigrate as $legacyMigration) {
            $name = $legacyMigration['version'];

            $path = $this->getLegacyMigrationDefinition($legacyMigration['version'], $legacyMigration['bundle']);
            if ($path != false) {
                $name = basename($path);
                $content = file_get_contents($path);
                $md5 = md5($content);
            } else {
                $path = 'unknown';
                $content = '';
                $md5 = 'unknown';
            }

            // take care: what if the migration already exists in the v2 table ???
            $existingMigration = $migrationStorageService->loadMigration($name);
            if ($existingMigration != null) {
                $output->writeln("<info>Info for migration version: {$legacyMigration['bundle']} {$legacyMigration['version']} was already migrated, skipping it</info>");
                continue;
            }

            $migrationDefinition = new MigrationDefinition(
                $name, $path, $content, MigrationDefinition::STATUS_PARSED
            );
            $migrationStorageService->startMigration($migrationDefinition);
            $migration = new Migration(
                $name,
                $md5,
                $path,
                $executionDate,
                Migration::STATUS_DONE
            );
            $migrationStorageService->endMigration($migration);

            // we leave legacy info in the legacy table, in case someone wants to roll back in the future...
            //$this->deleteLegacyMigration($legacyMigration['version'], $legacyMigration['bundle']);

            $output->writeln("Updated info for migration version: {$legacyMigration['bundle']} {$legacyMigration['version']}");
        }

        $output->writeln("<info>All known legacy migration versions have been migrated to the v2 database table</info>");
    }

    private function tableExist($tableName)
    {
        /** @var \Doctrine\DBAL\Schema\AbstractSchemaManager $sm */
        $sm = $this->dbHandler->getConnection()->getSchemaManager();
        foreach($sm->listTables() as $table) {
            if ($table->getName() == $tableName) {
                return true;
            }
        }

        return false;
    }

    private function loadLegacyMigrations()
    {
        /** @var \eZ\Publish\Core\Persistence\Database\SelectQuery $q */
        $q = $this->dbHandler->createSelectQuery();
        $q->select('version, bundle')
            ->from($this->legacyTableName)
            ->orderBy('version', SelectQuery::ASC);
        $stmt = $q->prepare();
        $stmt->execute();
        $results = $stmt->fetchAll();

        return $results;
    }

    private function deleteLegacyMigration($version, $bundle)
    {
        $this->dbHandler->getConnection()->delete($this->legacyTableName, array('version' => $version, 'bundle' => $bundle));
    }

    /**
     * Attempts to find the migration definition file. If more than one matches, the 1st found is returned
     * @param string $version
     * @param string $bundle
     * @return string|false
     */
    private function getLegacyMigrationDefinition($version, $bundle)
    {
        if (!isset($this->activeBundles[$bundle])) {
            return false;
        }

        $versionsDir = $this->activeBundles[$bundle] . '/' . $this->legacyMigrationsDir;
        $versionDefinitions = glob($versionsDir . "/$version*");

        if (!is_array($versionDefinitions)) {
            return false;
        }

        foreach($versionDefinitions as $key => $versionDefinition) {
            if (!in_array(pathinfo($versionDefinition, PATHINFO_EXTENSION), array('php', 'yml', 'sql'))) {
                unset($versionDefinitions[$key]);
            }
        }

        if (empty($versionDefinitions)) {
            return false;
        }

        return $versionDefinitions[0];
    }
}
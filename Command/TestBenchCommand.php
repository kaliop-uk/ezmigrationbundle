<?php

namespace Kaliop\eZMigrationBundle\Command;

use eZ\Publish\API\Repository\Repository;
use Kaliop\eZMigrationBundle\Core\MigrationService;
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
class TestBenchCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('kaliop:migration:test')
            ->setDescription('TEST of TRANSACTIONS')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Repository $repo */
        $repo = $this->getContainer()->get('ezpublish.api.repository');

        $user = $repo->getUserService()->loadUser(14);
        $repo->setCurrentUser($user);

        $ls = $repo->getLocationService();
        $location = $ls->loadLocation(140);
        $ls->hideLocation($location);
        return;

        /** @var MigrationService $ms */
        $ms = $this->getContainer()->get('ez_migration_bundle.migration_service');
/*echo 'A';
        $repo->commitEvent(
            function ( $lastEvent )
            {
                throw new \Exception('hahaha');
            }
        );
echo 'B';*/

        $ms->executeMigration(new MigrationDefinition(
            'test_'.time(),
            '/hello',
            '123',
            MigrationDefinition::STATUS_PARSED,
            array(),
            str_repeat('.', 4096)
        ));

        return;
        ///

        $sh = $this->getContainer()->get('ez_migration_bundle.storage_handler');

        $m1 = 'hello_'.time();
        $m2 = 'there_'.time();
        $sh->startMigration(new MigrationDefinition(
            $m1,
            '/hello',
            '123'
        ));
        $sh->startMigration(new MigrationDefinition(
            $m2,
            '/there',
            '123'
        ));

        $repo->beginTransaction();

        $repo->beginTransaction();
        //$repo->commit();
        $repo->rollback();

        $sh->endMigration(new Migration(
            $m2,
            '123',
            '/there',
            time(),
            0
        ));
        $sh->endMigration(new Migration(
            $m1,
            '123',
            '/hello',
            time(),
            0
        ));

        $repo->commit();

        echo "OK\n";

        ///

        $dbHandler = $this->getContainer()->get('ezpublish.connection');
        $conn = $dbHandler->getConnection();
        $conn->setNestTransactionsWithSavepoints(true);

        $repo->beginTransaction();
        $repo->commit();
        $repo->beginTransaction();
        $repo->rollback();
        echo "BCBR: OK\n";

        $repo->beginTransaction();
        $repo->rollback();
        $repo->beginTransaction();
        $repo->commit();
        echo "BRBC: OK\n";

        $repo->beginTransaction();
        $repo->beginTransaction();
        $repo->commit();
        $repo->commit();
        echo "BBCC: OK\n";

        $repo->beginTransaction();
        $repo->beginTransaction();
        $repo->commit();
        $repo->rollback();
        echo "BBCR: OK\n";

        $repo->beginTransaction();
        $repo->beginTransaction();
        $repo->rollback();
        $repo->rollback();
        echo "BBRR: OK\n";

        // will throw unless partial rollbacks (savepoints) are enabled
        $repo->beginTransaction();
        $repo->beginTransaction();
        $repo->rollback();
        $repo->beginTransaction();
        $repo->commit();
        $repo->commit();
        echo "BBRBCC: OK\n";

        // will throw unless partial rollbacks (savepoints) are enabled
        $repo->beginTransaction();
        $repo->beginTransaction();
        $repo->rollback();
        $repo->commit();
        echo "BBRC: OK\n";
    }
}

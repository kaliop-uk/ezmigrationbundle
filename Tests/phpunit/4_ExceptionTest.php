<?php

include_once(__DIR__.'/CommandTest.php');

use Kaliop\eZMigrationBundle\API\ExecutorInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationAbortedException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;

/**
 * Tests the MigrationAbortedException, as well as direct manipulation of the migration service
 */
class ExceptionTest extends CommandTest implements ExecutorInterface
{
    public function testMigrationAbortedException()
    {
        $ms = $this->container->get('ez_migration_bundle.migration_service');
        $ms->addExecutor($this);
        $md = new MigrationDefinition(
            'exception_test.json',
            '/dev/null',
            json_encode(array(array('type' => 'abort')))
        );
        $ms->executeMigration($md);

        $m = $ms->getMigration('exception_test.json');
        $this->assertEquals(Migration::STATUS_DONE, $m->status);
        $this->assertContains('Oh yeah', $m->executionError);
    }

    public function supportedTypes()
    {
        return array('abort');
    }

    public function execute(MigrationStep $step)
    {
        throw new MigrationAbortedException('Oh yeah');
    }
}

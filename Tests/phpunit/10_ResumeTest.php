<?php

include_once(__DIR__.'/MigrationExecutingTest.php');

use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\Tests\helper\BeforeStepExecutionListener;
use Kaliop\eZMigrationBundle\Tests\helper\StepExecutedListener;

/**
 * Tests the `resume` command
 */
class ResumeTest extends MigrationExecutingTest
{
    public function testSuspend()
    {
        $filePath = $this->dslDir.'/resume/UnitTestOK1001_suspend.yml';

        $ms = $this->getContainer()->get('ez_migration_bundle.migration_service');

        // Make sure migration is not in the db: delete it, ignoring errors
        $this->prepareMigration($filePath);

        $count1 = BeforeStepExecutionListener::getExecutions();
        $count2 = StepExecutedListener::getExecutions();

        $output = $this->runCommand('kaliop:migration:migrate', array('--path' => array($filePath), '-n' => true, '-u' => true));

        $count3 = BeforeStepExecutionListener::getExecutions();
        $count4 = StepExecutedListener::getExecutions();
        $this->assertEquals($count1 + 2, $count3, "Migration not suspended? incorrect number of steps executed");
        $this->assertEquals($count2 + 1, $count4, "Migration not suspended? incorrect number of steps executed");

        $m = $ms->getMigration(basename($filePath));
        $this->assertEquals($m->status, Migration::STATUS_SUSPENDED, 'Migration supposed to be suspended but in unexpected state');

        $output = $this->runCommand('kaliop:migration:resume', array('--set-reference' => array('kmb_test_901:notyet'), '-n' => true, '-u' => true));

        $m = $ms->getMigration(basename($filePath));
        $this->assertEquals($m->status, Migration::STATUS_SUSPENDED, 'Migration supposed to be suspended but in unexpected state');

        # Tests issue #162
        $output = $this->runCommand('kaliop:migration:resume', array('--set-reference' => array('kmb_test_901:world'), '-n' => true, '-u' => true));

        $m = $ms->getMigration(basename($filePath));
        $this->assertEquals($m->status, Migration::STATUS_DONE, 'Migration supposed to be completed but in unexpected state');

        $this->deleteMigration($filePath);
    }
}

<?php

include_once(__DIR__.'/MigrationExecutingTest.php');

use Kaliop\eZMigrationBundle\API\ExecutorInterface;
use Kaliop\eZMigrationBundle\API\Exception\MigrationAbortedException;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;
use Kaliop\eZMigrationBundle\API\Value\MigrationDefinition;
use Kaliop\eZMigrationBundle\API\Value\Migration;

class ExceptionsTest extends MigrationExecutingTest implements ExecutorInterface
{
    /**
     * Tests the MigrationAbortedException, as well as direct manipulation of the migration service
     */
    public function testMigrationCancelledException()
    {
        $ms = $this->getContainer()->get('ez_migration_bundle.migration_service');
        $ms->addExecutor($this);
        $md = new MigrationDefinition(
            'exception_test.json',
            '/dev/null',
            json_encode(array(array('type' => 'cancel')))
        );
        $ms->executeMigration($md);

        $m = $ms->getMigration('exception_test.json');
        $this->assertEquals(Migration::STATUS_DONE, $m->status, 'Migration in unexpected state');
        $this->assertContains('Willfully cancelled', $m->executionError, 'Migration aborted but its exception message lost');

        $this->runCommand('kaliop:migration:migration', array('migration' => 'exception_test.json', '--delete' => true, '-n' => true));
    }

    /// @todo same as above, but with MigrationAbortedException 'fail' mode

    /// @todo test of generic exception ('throw' step)

    /// @todo do a similar test but using Anonymous user
    public function testInvalidUserAccountException()
    {
        //$bundles = $this->getContainer()->getParameter('kernel.bundles');
        $ms = $this->getContainer()->get('ez_migration_bundle.migration_service');

        $filePath = $this->dslDir . '/misc/UnitTestOK701_loadSomething.yml';

        $this->prepareMigration($filePath);

        $input = $this->buildInput('kaliop:migration:migrate', array('--path' => array($filePath), '-n' => true, '-u' => true, '--admin-login' => 123456789));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertNotEquals(0, $exitCode, 'CLI Command succeeded instead of failing. Output: ' . $output);
        $this->assertContains('Could not find the required user account to be used for logging in', $output, 'Migration aborted but its exception message lost');

        $m = $ms->getMigration(basename($filePath));
        $this->assertEquals($m->status, Migration::STATUS_FAILED, 'Migration supposed to be failed but in unexpected state');

        $this->deleteMigration($filePath);
    }

    // ### ExecutorInterface ###

    public function supportedTypes()
    {
        return array('cancel', 'fail', 'throw', 'crash');
    }

    public function execute(MigrationStep $step)
    {
        switch($step->type) {
            case 'cancel':
                throw new MigrationAbortedException('Willfully cancelled');
            case 'fail':
                throw new MigrationAbortedException('Willfully failed', Migration::STATUS_FAILED);
            case 'throw':
                throw new \Exception('Willfully crashed');
            case 'crash':
                callAFunctionWhichDoesNotExist('hopefully ;-)');
        }
    }
}

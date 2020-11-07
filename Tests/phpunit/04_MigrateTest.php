<?php

include_once(__DIR__.'/MigrationExecutingTest.php');

use Kaliop\eZMigrationBundle\API\Value\Migration;
use Kaliop\eZMigrationBundle\Tests\helper\BeforeStepExecutionListener;
use Kaliop\eZMigrationBundle\Tests\helper\StepExecutedListener;

/**
 * Tests the 'kaliop:migration:migrate' and (partially) 'kaliop:migration:migration' commands
 */
class MigrateTest extends MigrationExecutingTest
{
    /**
     * @param string $filePath
     * @dataProvider goodDSLProvider
     */
    public function testExecuteGoodDSL($filePath = '')
    {
        if ($filePath == '') {
            $this->markTestSkipped();
            return;
        }

        $this->prepareMigration($filePath);

        $count1 = BeforeStepExecutionListener::getExecutions();
        $count2 = StepExecutedListener::getExecutions();

        $output = $this->runCommand('kaliop:migration:migrate', array('--path' => array($filePath), '-n' => true, '-u' => true));
        // check that there are no notes related to adding the migration before execution
        $this->assertRegexp('?\| ' . basename($filePath) . ' +\| +\|?', $output);

        // simplistic check on the event listeners having fired off correctly
        $this->assertGreaterThanOrEqual($count1 + 1, BeforeStepExecutionListener::getExecutions(), "Migration 'before step' listener did not fire");
        $this->assertGreaterThanOrEqual($count2 + 1, StepExecutedListener::getExecutions(), "Migration 'step executed' listener did not fire");

        $this->deleteMigration($filePath);
    }

    /**
     * @param string $filePath
     * @dataProvider invalidDSLProvider
     */
    public function testExecuteInvalidDSL($filePath = '')
    {
        if ($filePath == '') {
            $this->markTestSkipped();
            return;
        }

        $this->prepareMigration($filePath);

        $output = $this->runCommand('kaliop:migration:migrate', array('--path' => array($filePath), '-n' => true, '-u' => true));
        // check that the mig has been skipped
        $this->assertRegexp('?Skipping ' . basename($filePath) . '?', $output);

        $this->deleteMigration($filePath);
    }

    /**
     * @param string $filePath
     * @dataProvider badDSLProvider
     */
    public function testExecuteBadDSL($filePath = '')
    {
        if ($filePath == '') {
            $this->markTestSkipped();
            return;
        }

        $this->prepareMigration($filePath);

        $input = $this->buildInput('kaliop:migration:migrate', array('--path' => array($filePath), '-n' => true, '-u' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertNotEquals(0, $exitCode, 'CLI Command should have failed. Output: ' . $output);
        // check that the mig failed
        $this->assertRegexp('?Migration failed!?', $output);

        $this->deleteMigration($filePath);
    }

    /**
     * Tests the --default-language option for the migrate command.
     */
    public function testDefaultLanguage()
    {
        $filePath = $this->dslDir . '/misc/UnitTestOK018_defaultLanguage.yml';
        $defaultLanguage = 'def-LA';

        $this->deleteMigration($filePath, false);

        $output = $this->runCommand('kaliop:migration:migrate', array(
            '--path' => array($filePath),
            '-n' => true,
            '-u' => true,
            '--default-language' => $defaultLanguage,
        ));

        $repository = $this->getRepository();
        $contentService = $repository->getContentService();

        // check that the 1st content was created with the yml-specified language
        $content = $contentService->loadContentByRemoteId('kmb_test_18_content_1', null, null, false);
        $this->assertInstanceOf('eZ\Publish\API\Repository\Values\Content\Content', $content);
        $this->assertSame('eng-GB', $content->contentInfo->mainLanguageCode);

        // check that the 2nd content was created with the default language from cli
        $content = $contentService->loadContentByRemoteId('kmb_test_18_content_2', [$defaultLanguage], null, false);
        $this->assertInstanceOf('eZ\Publish\API\Repository\Values\Content\Content', $content);
        $this->assertSame($defaultLanguage, $content->contentInfo->mainLanguageCode);

        // cleanup
        $contentService->deleteContent($content->contentInfo);
        $contentService->deleteContent($contentService->loadContentInfoByRemoteId('kmb_test_18_content_1'));

        $contentTypeService = $repository->getContentTypeService();
        $contentTypeService->deleteContentType($contentTypeService->loadContentTypeByIdentifier('kmb_test_18'));

        $langService = $repository->getContentLanguageService();
        $langService->deleteLanguage($langService->loadLanguage($defaultLanguage));

        $this->deleteMigration($filePath);
    }

    public function testSetRef()
    {
        $filePath = $this->dslDir.'/misc/UnitTestOK031_helloworld.yml';

        $ms = $this->getContainer()->get('ez_migration_bundle.migration_service');

        // Make sure migration is not in the db: delete it, ignoring errors
        $this->prepareMigration($filePath);

        $output = $this->runCommand(
            'kaliop:migration:migrate',
            array('--path' => array($filePath), '-n' => true, '-u' => true, '--set-reference' => array('yo:lo', 'kmb_test_31_ref:hello'))
        );

        $this->assertContains('hello', $output);

        $this->deleteMigration($filePath);
    }

    public function testCancelledExecution()
    {
        $filePath = $this->dslDir.'/misc/UnitTestOK019_cancel.yml';

        $ms = $this->getContainer()->get('ez_migration_bundle.migration_service');

        // Make sure migration is not in the db: delete it, ignoring errors
        $this->prepareMigration($filePath);

        $count1 = BeforeStepExecutionListener::getExecutions();
        $count2 = StepExecutedListener::getExecutions();

        $output = $this->runCommand('kaliop:migration:migrate', array('--path' => array($filePath), '-n' => true, '-u' => true));

        $count3 = BeforeStepExecutionListener::getExecutions();
        $count4 = StepExecutedListener::getExecutions();
        $this->assertEquals($count1 + 2, $count3, "Migration not canceled? incorrect number of steps executed");
        $this->assertEquals($count2 + 1, $count4, "Migration not canceled? incorrect number of steps executed");

        $m = $ms->getMigration(basename($filePath));
        $this->assertEquals($m->status, Migration::STATUS_DONE, 'Migration supposed to be cancelled but in unexpected state');

        $this->deleteMigration($filePath);
    }

    public function testFailedExecution()
    {
        $filePath = $this->dslDir.'/misc/UnitTestOK020_fail.yml';

        $ms = $this->getContainer()->get('ez_migration_bundle.migration_service');

        // Make sure migration is not in the db: delete it, ignoring errors
        $this->prepareMigration($filePath);

        $count1 = BeforeStepExecutionListener::getExecutions();
        $count2 = StepExecutedListener::getExecutions();

        $output = $this->runCommand(
            'kaliop:migration:migrate',
            array('--path' => array($filePath), '-n' => true, '-u' => true),
            false
        );

        $count3 = BeforeStepExecutionListener::getExecutions();
        $count4 = StepExecutedListener::getExecutions();
        $this->assertEquals($count1 + 2, $count3, "Migration not failed? incorrect number of steps executed");
        $this->assertEquals($count2 + 1, $count4, "Migration not failed? incorrect number of steps executed");

        $m = $ms->getMigration(basename($filePath));
        $this->assertEquals($m->status, Migration::STATUS_FAILED, 'Migration supposed to be failed but in unexpected state');

        $this->deleteMigration($filePath);
    }

    /**
     * Tests executing a very simple migration with all the different cli flags enabled or not
     * @param array $options
     * @dataProvider migrateCommandOptionsProvider
     */
    public function testExecuteWithDifferentOptions(array $options = array())
    {
        $filePath = $this->dslDir . '/misc/UnitTestOK021_helloworld.yml';

        $this->deleteMigration($filePath, false);

        $output = $this->runCommand('kaliop:migration:migrate', array_merge(array('--path' => array($filePath), '-n' => true), $options));

        // check that the output contains the expected output, in quiet mode/verbose/very_verbose modes
        /// @todo fix usage of output buffering and stream handlers to allow matching the reference dump
        if (array_key_exists('-q', $options)) {
            //$this->assertNotRegExp('/"hello world"/', $output, 'Migration output unexpected');
            $this->assertNotRegExp('/Time taken:/', $output, 'Migration output unexpected');
        } else if (array_key_exists('-v', $options)) {
            //$this->assertRegExp('/"hello world"/', $output, 'Migration output unexpected');
            $this->assertRegExp('/migration step.+has been executed/', $output, 'Migration output unexpected');
            $this->assertRegExp('/Time taken:/', $output, 'Migration output unexpected');
        } else if (array_key_exists('-vv', $options)) {
            //$this->assertRegExp('/"hello world"/', $output, 'Migration output unexpected');
            $this->assertRegExp('/migration step.+will be executed/', $output, 'Migration output unexpected');
            $this->assertRegExp('/migration step.+has been executed/', $output, 'Migration output unexpected');
            $this->assertRegExp('/memory delta:/', $output, 'Migration output unexpected');
            $this->assertRegExp('/Time taken:/', $output, 'Migration output unexpected');
        }

        /// @todo add some assertion on the output of `--info`: move to a separate test
        $output = $this->runCommand('kaliop:migration:migration', array('migration' => basename($filePath), '--info' => true, '-n' => true));

        $this->deleteMigration($filePath);
    }

    public function goodDSLProvider()
    {
        $dslDir = $this->dslDir.'/good';
        if (!is_dir($dslDir)) {
            return array();
        }

        $out = array();
        foreach (scandir($dslDir) as $fileName) {
            $filePath = $dslDir . '/' . $fileName;
            if (is_file($filePath)) {
                $out[] = array($filePath);
            }
        }
        return $out;
    }

    public function invalidDSLProvider()
    {
        $dslDir = $this->dslDir.'/bad/parsing';
        if (!is_dir($dslDir)) {
            return array();
        }

        $out = array();
        foreach (scandir($dslDir) as $fileName) {
            $filePath = $dslDir . '/' . $fileName;
            if (is_file($filePath)) {
                $out[] = array($filePath);
            }
        }
        return $out;
    }

    public function badDSLProvider()
    {
        $dslDir = $this->dslDir.'/bad/execution';
        if (!is_dir($dslDir)) {
            return array();
        }

        $out = array();
        foreach (scandir($dslDir) as $fileName) {
            $filePath = $dslDir . '/' . $fileName;
            if (is_file($filePath)) {
                $out[] = array($filePath);
            }
        }
        return $out;
    }

    public function migrateCommandOptionsProvider()
    {
        return array(
            array(array()),
            /// @todo re-enable these 2 tests after we find a way to make them not fail with recent versions of Sf  (or is it phpunit?):
            ///       it seems that after clearing the cache once, further code fails trying to access the debug.dump_listener
            ///       service after the console StreamOutput writer fails to write to its stream and throws
            ///       PHP Fatal error:  require(): Failed opening required '/home/travis/build/kaliop-uk/ezmigrationbundle/vendor/ezsystems/ezplatform/var/cache/beha_/ContainerIkrz2xn/getDebug_DumpListenerService.php'. in /home/travis/build/kaliop-uk/ezmigrationbundle/vendor/ezsystems/ezplatform/var/cache/behat/ContainerIkrz2xn/appBehatProjectContainer.php on line 5166
            ///       PHP Fatal error:  Uncaught Symfony\Component\Console\Exception\RuntimeException: Unable to write output. in /home/travis/build/kaliop-uk/ezmigrationbundle/vendor/symfony/symfony/src/Symfony/Component/Console/Output/StreamOutput.php:79
            //array(array('-c' => true)),
            //array(array('--clear-cache' => true)),
            array(array('-f' => true)),
            array(array('--force' => true)),
            array(array('-i' => true)),
            array(array('--ignore-failures' => true)),
            array(array('-u' => true)),
            array(array('--no-transactions' => true)),
            array(array('-p' => true)),
            array(array('--separate-process' => true)),
            array(array('--force-sigchild-enabled' => true)),
            array(array('--survive-disconnected-tty' => true)),
            array(array('--set-reference' => array('hello:world'))),
            array(array('-q' => true)),
            array(array('-v' => true)),
            array(array('-vv' => true)),
            array(array('-q' => true, '-p' => true)),
            array(array('-v' => true, '-p' => true)),
            array(array('-vv' => true, '-p' => true)),
            /// @todo add tests for `a`, `l` flags
        );
    }

    /**
     * Get the eZ repository
     * @param int $loginUserId
     * @return \eZ\Publish\Core\SignalSlot\Repository
     */
    protected function getRepository($loginUserId = \Kaliop\eZMigrationBundle\Core\MigrationService::ADMIN_USER_ID)
    {
        $repository = $this->getContainer()->get('ezpublish.api.repository');
        if ($loginUserId !== false && (is_null($repository->getCurrentUser()) || $repository->getCurrentUser()->id != $loginUserId)) {
            $repository->setCurrentUser($repository->getUserService()->loadUser($loginUserId));
        }

        return $repository;
    }
}

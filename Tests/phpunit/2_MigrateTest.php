<?php

include_once(__DIR__.'/CommandTest.php');

use Symfony\Component\Console\Input\ArrayInput;
use Kaliop\eZMigrationBundle\Tests\helper\BeforeStepExecutionListener;
use Kaliop\eZMigrationBundle\Tests\helper\StepExecutedListener;

/**
 * Tests the 'migrate' as well as the 'migration' command
 */
class MigrateTest extends CommandTest
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

        $input = new ArrayInput(array('command' => 'kaliop:migration:migrate', '--path' => array($filePath), '-n' => true, '-u' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        // check that there are no notes after adding the migration
        $this->assertRegexp('?\| ' . basename($filePath) . ' +\| +\|?', $output);

        // simplistic check on the event listeners having fired off correctly
        $this->assertGreaterThanOrEqual($count1 + 1, BeforeStepExecutionListener::getExecutions(), "Migration 'before step' listener did not fire");
        $this->assertGreaterThanOrEqual($count2 + 1, StepExecutedListener::getExecutions(), "Migration 'step executed' listener did not fire");

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
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

        $input = new ArrayInput(array('command' => 'kaliop:migration:migrate', '--path' => array($filePath), '-n' => true, '-u' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        // check that there are no notes after adding the migration
        $this->assertRegexp('?Skipping ' . basename($filePath) . '?', $output);

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
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

        $input = new ArrayInput(array('command' => 'kaliop:migration:migrate', '--path' => array($filePath), '-n' => true, '-u' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertNotSame(0, $exitCode, 'CLI Command should have failed. Output: ' . $output);
        // check that there are no notes after adding the migration
        $this->assertRegexp('?Migration aborted.?', $output);

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
    }

    /**
     * Tests the --default-language option for the migrate command.
     */
    public function testDefaultLanguage()
    {
        $filePath = $this->dslDir . '/UnitTestOK018_defaultLanguage.yml';
        $defaultLanguage = 'def-LA';

        $this->prepareMigration($filePath);

        $exitCode = $this->runCommand('kaliop:migration:migrate', array(
            '--path' => array($filePath),
            '-n' => true,
            '-u' => true,
            '--default-language' => $defaultLanguage,
        ));
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        // check that there are no notes after adding the migration
        $this->assertRegexp('?\| ' . basename($filePath) . ' +\| +\|?', $output);

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

    /**
     * Add a migration from a file to the migration service.
     * @param string $filePath
     */
    protected function addMigration($filePath)
    {
        $exitCode = $this->runCommand('kaliop:migration:migration', [
            'migration' => $filePath,
            '--add' => true,
            '-n' => true,
        ]);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->assertRegexp('?Added migration?', $output);
    }

    /**
     * Delete the migration from the database table
     * @param string $filePath
     * @return string
     */
    protected function deleteMigration($filePath)
    {
        $this->runCommand('kaliop:migration:migration', [
            'migration' => basename($filePath),
            '--delete' => true,
            '-n' => true,
        ]);

        return $this->fetchOutput();
    }

    /**
     * Prepare a migration file for a test.
     * @param string $filePath
     */
    protected function prepareMigration($filePath)
    {
        // Make user migration is not in the db: delete it, ignoring errors
        $this->deleteMigration($filePath);
        $this->addMigration($filePath);
    }

    /**
     * Run a symfony command
     * @param string $commandName
     * @param array $params
     * @return int
     */
    protected function runCommand($commandName, array $params)
    {
        $params = array_merge(['command' => $commandName], $params);
        $input = new ArrayInput($params);

        return $this->app->run($input, $this->output);
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

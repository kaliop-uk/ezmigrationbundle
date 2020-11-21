<?php

include_once(__DIR__.'/MigrationExecutingTest.php');

/**
 * Tests the `status` command
 */
class StatusTest extends MigrationExecutingTest
{
    public function testSummary()
    {
        $output = $this->runCommand('kaliop:migration:status', array('--summary' => true));
        $this->assertRegexp('?\| Invalid +\| \d+ +\|?', $output);
        $this->assertRegexp('?\| To do +\| \d+ +\|?', $output);
        $this->assertRegexp('?\| Started +\| \d+ +\|?', $output);
        $this->assertRegexp('?\| Started +\| \d+ +\|?', $output);
        $this->assertRegexp('?\| Done +\| \d+ +\|?', $output);
        $this->assertRegexp('?\| Suspended +\| \d+ +\|?', $output);
        $this->assertRegexp('?\| Failed +\| \d+ +\|?', $output);
        $this->assertRegexp('?\| Skipped +\| \d+ +\|?', $output);
    }

    // Tests issue #190
    public function testSorting()
    {
        $filePath1 = $this->dslDir.'/misc/UnitTestOK701_harmless.yml';
        $filePath2 = $this->dslDir.'/misc/UnitTestOK702_harmless.yml';

        $this->prepareMigration($filePath1);
        $this->prepareMigration($filePath2);

        $output = $this->runCommand('kaliop:migration:migrate', array('--path' => array($filePath2), '-n' => true, '-u' => true));
        // check that there are no notes related to adding the migration before execution
        $this->assertRegexp('?\| ' . basename($filePath2) . ' +\| +\|?', $output);
        sleep(1);
        $output = $this->runCommand('kaliop:migration:migrate', array('--path' => array($filePath1), '-n' => true, '-u' => true));
        // check that there are no notes related to adding the migration before execution
        $this->assertRegexp('?\| ' . basename($filePath1) . ' +\| +\|?', $output);

        $output = $this->runCommand('kaliop:migration:status');
        $this->assertRegexp('?\| ' . basename($filePath1) . ' +\| executed +\|.+\| ' . basename($filePath2) . ' +\| executed +\|?s', $output);

        $output = $this->runCommand('kaliop:migration:status', array('--sort-by' => 'execution'));
        $this->assertRegexp('?\| ' . basename($filePath2) . ' +\| executed +\|.+\| ' . basename($filePath1) . ' +\| executed +\|?s', $output);

        $this->deleteMigration($filePath1);
        $this->deleteMigration($filePath2);
    }

    public function testTodo()
    {
        $filePath = $this->dslDir.'/misc/UnitTestOK701_harmless.yml';
        $this->prepareMigration($filePath);
        $output = $this->runCommand('kaliop:migration:status', array('--todo' => true));
        $this->assertRegexp('?^'.$filePath.'$?m', $output);

        $this->deleteMigration($filePath);
    }

    public function testPath()
    {
        $filePath = $this->dslDir.'/misc/UnitTestOK701_harmless.yml';
        $this->deleteMigration($filePath, false);

        $output = $this->runCommand('kaliop:migration:status');
        $this->assertNotContains(basename($filePath), $output);

        $output = $this->runCommand('kaliop:migration:status', array('--path' => array($filePath)));
        $this->assertContains(basename($filePath), $output);
        $this->assertNotContains('20100101000200_MigrateV1ToV2.php', $output);

        $this->addMigration($filePath);
        $output = $this->runCommand('kaliop:migration:status');
        $this->assertContains(basename($filePath), $output);

        $this->deleteMigration($filePath);
    }

    public function testShowPath()
    {
        $filePath = $this->dslDir.'/misc/UnitTestOK701_harmless.yml';
        $this->deleteMigration($filePath, false);

        $output = $this->runCommand('kaliop:migration:status', array('--show-path' => true, '--path' => array($filePath)));
        $this->assertContains($filePath, $output);

        $this->addMigration($filePath);
        $output = $this->runCommand('kaliop:migration:status', array('--show-path' => true));
        $this->assertContains($filePath, $output);

        $this->deleteMigration($filePath);
    }
}

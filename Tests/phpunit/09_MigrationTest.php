<?php

include_once(__DIR__.'/MigrationExecutingTest.php');

/**
 * Tests the `migration` command (note that --add and --delete actions are already widely tested elsewhere)
 */
class MigrationTest extends MigrationExecutingTest
{
    public function testInfo()
    {
        $filePath = $this->dslDir.'/misc/UnitTestOK901_harmless.yml';
        $this->prepareMigration($filePath);

        $output = $this->runCommand('kaliop:migration:migration', array('--info' => true, 'migration' => basename($filePath)));
        $this->assertContains('Migration: '.basename($filePath), $output);
        $this->assertContains('Status: not executed', $output);

        $this->deleteMigration($filePath);
    }

    public function testSkip()
    {
        $filePath = $this->dslDir.'/misc/UnitTestOK901_harmless.yml';
        $this->deleteMigration($filePath, false);

        $output = $this->runCommand('kaliop:migration:migration', array('--skip' => true, '-n' => true, 'migration' => $filePath));

        $output = $this->runCommand('kaliop:migration:migration', array('--info' => true, 'migration' => basename($filePath)));
        $this->assertContains('Migration: '.basename($filePath), $output);
        $this->assertContains('Status: skipped', $output);

        $output = $this->runCommand('kaliop:migration:migrate', array('--path' => array($filePath)));
        $this->assertContains('No migrations to execute', $output);

        $this->deleteMigration($filePath);
    }

    public function testDelete()
    {
        $filePath = $this->dslDir.'/misc/UnitTestOK901_harmless.yml';
        $this->prepareMigration($filePath);

        $output = $this->runCommand('kaliop:migration:status');
        $this->assertContains(basename($filePath), $output);

        $output = $this->runCommand('kaliop:migration:migration', array('--delete' => true, '-n' => true, 'migration' => basename($filePath)));

        $output = $this->runCommand('kaliop:migration:status');
        $this->assertNotContains(basename($filePath), $output);
    }

    /// @todo move to ExceptionTests ?
    public function testAddSameMigrationTwice()
    {
        $filePath = $this->dslDir.'/misc/UnitTestOK901_harmless.yml';
        $this->prepareMigration($filePath);

        $exitCode = $this->app->run($this->buildInput('kaliop:migration:migration', array('--add' => true, '-n' => true, 'migration' => $filePath)), $this->output);
        $output = $this->fetchOutput();
        $this->assertNotEquals(0, $exitCode);
        $this->assertContains('already exists', $output);

        $this->deleteMigration($filePath);
    }
}

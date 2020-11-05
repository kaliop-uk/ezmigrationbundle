<?php

include_once(__DIR__.'/CommandTest.php');

use Symfony\Component\Console\Input\ArrayInput;

abstract class MigrationTest extends \CommandTest
{
    /**
     * Add a migration from a file to the list of known ones in the db; this involves parsing it for syntax errors
     * @param string $filePath
     * @return string
     * @throws \Exception
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

        return $output;
    }

    /**
     * Delete the migration from the database table
     * @param string $filePath
     * @param bool $checkExitCode
     * @return string
     * @throws \Exception
     */
    protected function deleteMigration($filePath, $checkExitCode = true)
    {
        $exitCode = $this->runCommand('kaliop:migration:migration', [
            'migration' => basename($filePath),
            '--delete' => true,
            '-n' => true,
        ]);

        $output = $this->fetchOutput();

        if ($checkExitCode) {
            $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        }

        return $output;
    }

    /**
     * Prepare a migration file for a test: remove it if needed from list of previously executed and add it to the db,
     * so that it gets parsed for syntax errors
     * @param string $filePath
     * @throws \Exception
     * @todo find a name indicating more clearly what this does
     */
    protected function prepareMigration($filePath)
    {
        // Make sure migration is not in the db: delete it, ignoring errors
        $this->deleteMigration($filePath, false);
        $this->addMigration($filePath);
    }

    /**
     * Run a symfony command
     * @param string $commandName
     * @param array $params
     * @return int
     * @throws \Exception
     */
    protected function runCommand($commandName, array $params)
    {
        $params = array_merge(['command' => $commandName], $params);
        $input = new ArrayInput($params);

        return $this->app->run($input, $this->output);
    }
}

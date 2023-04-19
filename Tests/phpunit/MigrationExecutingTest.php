<?php

include_once(__DIR__.'/CommandExecutingTest.php');

use Kaliop\eZMigrationBundle\API\Value\Migration;

abstract class MigrationExecutingTest extends CommandExecutingTest
{
    protected $dslDir;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        // seems like this can not be used outside of the constructor...
        $this->dslDir = __DIR__ . '/../dsl';
    }

    /**
     * Add a migration from a file to the list of known ones in the db; this involves parsing it for syntax errors
     * @param string $filePath
     * @return string
     * @throws \Exception
     * @todo eschew usage of the 'migration' command for speed and to increase test separation; add a separate 'migration' test for add
     */
    protected function addMigration($filePath)
    {
        $output = $this->runCommand('kaliop:migration:migration', [
            'migration' => $filePath,
            '--add' => true,
            '-n' => true,
        ]);

        $this->assertRegexp('?Added migration?', $output);

        return $output;
    }

    /**
     * Delete the migration from the database table
     * @param string $filePath
     * @param bool $checkExitCode
     * @return string
     * @throws \Exception
     * @todo eschew usage of the 'migration' command for speed and to increase test separation; add a separate 'migration' test for delete
     */
    protected function deleteMigration($filePath, $checkExitCode = true)
    {
        return $this->runCommand(
            'kaliop:migration:migration',
            [
                'migration' => basename($filePath),
                '--delete' => true,
                '-n' => true,
            ],
            $checkExitCode
        );
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


    protected function executeMigration($filePath, $flags = array(), $expectedStatus = Migration::STATUS_DONE, $checkExitCode = true)
    {
        $output = $this->runCommand(
            'kaliop:migration:migrate',
            array_merge($flags, ['--path' => [$filePath], '-n' => true]),
            $checkExitCode
        );

        $this->assertRegexp('?Processing ' . preg_quote(basename($filePath), '?') . '?', $output);

        $ms = $this->getContainer()->get('ez_migration_bundle.migration_service');
        $m = $ms->getMigration(basename($filePath));

        if ($expectedStatus !== false && $expectedStatus !== null) {
            $this->assertEquals($m->status, $expectedStatus, 'Migration in unexpected state after execution');
        }

        return $m;
    }

    /**
     * Run a migration, checking that it executes ok, then remove it from the migrations table
     * @param string $filePath
     * @param array $flags
     * @param null|int $expectedStatus
     * @return Migration
     * @throws \Exception
     */
    protected function runMigration($filePath, $flags = array(), $expectedStatus = Migration::STATUS_DONE, $checkExitCode = true)
    {
        $this->prepareMigration($filePath);
        $m = $this->executeMigration($filePath, $flags, $expectedStatus, $checkExitCode);
        $this->deleteMigration($filePath);
        return $m;
    }
}

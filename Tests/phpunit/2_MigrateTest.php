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

        // Make sure migration is not in the db: delete it, ignoring errors
        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $this->app->run($input, $this->output);
        $this->fetchOutput();

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => $filePath, '--add' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->assertRegexp('?Added migration?', $output);

        $count1 = BeforeStepExecutionListener::getExecutions();
        $count2 = StepExecutedListener::getExecutions();

        $input = new ArrayInput(array('command' => 'kaliop:migration:migrate', '--path' => array($filePath), '-n' => true, '-u' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        // check that there are no notes after adding the migration
        $this->assertRegexp('?\| ' . basename($filePath) . ' +\| +\|?', $output);

        // simplistic check on the event listeners having fired off correctly
        $this->assertGreaterThanOrEqual($count1 + 1, BeforeStepExecutionListener::getExecutions());
        $this->assertGreaterThanOrEqual($count2 + 1, StepExecutedListener::getExecutions());

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

        // Make user migration is not in the db: delete it, ignoring errors
        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $this->app->run($input, $this->output);
        $this->fetchOutput();

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => $filePath, '--add' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->assertRegexp('?Added migration?', $output);

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

        // Make user migration is not in the db: delete it, ignoring errors
        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $this->app->run($input, $this->output);
        $this->fetchOutput();

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => $filePath, '--add' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->assertRegexp('?Added migration?', $output);

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

    public function goodDSLProvider()
    {
        $dslDir = $this->dslDir.'/good';
        if(!is_dir($dslDir)) {
            return array();
        }

        $out = array();
        foreach(scandir($dslDir) as $fileName) {
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
        if(!is_dir($dslDir)) {
            return array();
        }

        $out = array();
        foreach(scandir($dslDir) as $fileName) {
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
        if(!is_dir($dslDir)) {
            return array();
        }

        $out = array();
        foreach(scandir($dslDir) as $fileName) {
            $filePath = $dslDir . '/' . $fileName;
            if (is_file($filePath)) {
                $out[] = array($filePath);
            }
        }
        return $out;
    }
}

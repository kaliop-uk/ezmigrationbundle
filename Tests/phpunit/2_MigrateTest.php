<?php

include_once(__DIR__.'/CommandTest.php');

use Symfony\Component\Console\Input\ArrayInput;

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

        // Make user migration is not in the db: delete it, ignoring errors
        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->output->fetch();

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => $filePath, '--add' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->output->fetch();
        $this->assertSame(0, $exitCode);
        $this->assertRegexp('?Added migration?', $output);

        $input = new ArrayInput(array('command' => 'kaliop:migration:migrate', '--path' => array($filePath), '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->output->fetch();
        $this->assertSame(0, $exitCode);
        // check that there are no notes after adding the migration
        $this->assertRegexp('?\| ' . basename($filePath) . ' +\| +\|?', $output);

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->output->fetch();
        $this->assertSame(0, $exitCode);
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
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->output->fetch();

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => $filePath, '--add' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->output->fetch();
        $this->assertSame(0, $exitCode);
        $this->assertRegexp('?Added migration?', $output);

        $input = new ArrayInput(array('command' => 'kaliop:migration:migrate', '--path' => array($filePath), '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->output->fetch();
        $this->assertSame(0, $exitCode);
        // check that there are no notes after adding the migration
        $this->assertRegexp('?Skipping ' . basename($filePath) . '?', $output);

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->output->fetch();
        $this->assertSame(0, $exitCode);
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

    public function badDSLProvider()
    {
        $dslDir = $this->dslDir.'/bad';
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

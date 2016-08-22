<?php

include_once(__DIR__.'/CommandTest.php');

use Symfony\Component\Console\Input\ArrayInput;

class GenerateTest extends CommandTest
{
    /**
     * @todo add tests for generating a 'role' migration
     */
    public function testGenerateDSL()
    {
        $input = new ArrayInput(array('command' => 'kaliop:migration:generate', 'bundle' => $this->targetBundle));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->checkGeneratedFile($this->saveGeneratedFile($output));

        $input = new ArrayInput(array('command' => 'kaliop:migration:generate', 'bundle' => $this->targetBundle, '--format' => 'sql'));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->checkGeneratedFile($this->saveGeneratedFile($output));

        $input = new ArrayInput(array('command' => 'kaliop:migration:generate', 'bundle' => $this->targetBundle, '--format' => 'php'));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->checkGeneratedFile($this->saveGeneratedFile($output));

        $input = new ArrayInput(array('command' => 'kaliop:migration:generate', 'bundle' => $this->targetBundle, 'name' => 'unit_test_generated'));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->checkGeneratedFile($this->saveGeneratedFile($output));

        $input = new ArrayInput(array('command' => 'kaliop:migration:generate', 'bundle' => $this->targetBundle, 'name' => 'unit_test_generated', '--format' => 'sql'));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->checkGeneratedFile($this->saveGeneratedFile($output));

        $input = new ArrayInput(array('command' => 'kaliop:migration:generate', 'bundle' => $this->targetBundle, 'name' => 'unit_test_generated', '--format' => 'php'));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->checkGeneratedFile($this->saveGeneratedFile($output));

        $input = new ArrayInput(array('command' => 'kaliop:migration:generate', 'bundle' => $this->targetBundle, 'name' => 'unit_test_generated_role', '--role' => 'Anonymous'));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->checkGeneratedFile($this->saveGeneratedFile($output));
    }

    protected function saveGeneratedFile($output)
    {
        if (preg_match('/Generated new migration file: +(.*)/', $output, $matches)) {
            $this->leftovers[] = $matches[1];
            return $matches[1];
        }

        return null;
    }

    protected function checkGeneratedFile($filePath)
    {
        $input = new ArrayInput(array('command' => 'kaliop:migration:status'));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->assertRegExp('?\| ' . basename($filePath) . ' +\| not executed +\|?', $output );
    }
}

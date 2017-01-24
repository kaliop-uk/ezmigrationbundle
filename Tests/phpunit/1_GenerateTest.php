<?php

include_once(__DIR__.'/CommandTest.php');

use Symfony\Component\Console\Input\ArrayInput;

class GenerateTest extends CommandTest
{
    /**
     * @dataProvider provideGenerateParameters
     */
    public function testGenerateDSL($name, $format, $type, $identifier, $mode)
    {
        $parameters = array(
            'command' => 'kaliop:migration:generate',
            'bundle' => $this->targetBundle
        );

        if ($name) {
            $parameters['name'] = $name;
        }

        if ($format) {
            $parameters['--format'] = $format;
        }

        if ($type) {
            $parameters['--type'] = $type;
        }

        if ($identifier) {
            $parameters['--identifier'] = $identifier;
        }

        if ($mode) {
            $parameters['--mode'] = $mode;
        }

        $input = new ArrayInput($parameters);
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        $this->checkGeneratedFile($this->saveGeneratedFile($output));
    }

    public function provideGenerateParameters()
    {
        return array(
            array(null, null, null, null, null),
            array(null, 'sql', null, null, null),
            array(null, 'php', null, null, null),
            array('unit_test_generated', null, null, null, null),
            array('unit_test_generated', 'sql', null, null, null),
            array('unit_test_generated', 'php', null, null, null),
            array('unit_test_generated_role', null, 'role', 'Anonymous', null),
            array('unit_test_generated_role', null, 'role', 'Anonymous', 'create'),
            array('unit_test_generated_role', null, 'role', 'Anonymous', 'update'),
            array('unit_test_generated_content_type', 'yml', 'content_type', 'folder', null),
            array('unit_test_generated_content_type', 'json', 'content_type', 'folder', 'update')
        );
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
        $this->assertRegExp('?\| ' . basename($filePath) . ' +\| not executed +\|?', $output);
    }
}

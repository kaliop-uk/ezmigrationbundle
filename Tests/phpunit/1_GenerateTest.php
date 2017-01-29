<?php

include_once(__DIR__.'/CommandTest.php');

use Symfony\Component\Console\Input\ArrayInput;

class GenerateTest extends CommandTest
{
    /**
     * @dataProvider provideGenerateParameters
     *
     * @param string|null $name
     * @param string|null $format
     * @param string|null $type
     * @param string|null $matchType
     * @param string|null $matchValue
     * @param string|null $mode
     */
    public function testGenerateDSL(
        $name = null,
        $format = null,
        $type = null,
        $matchType = null,
        $matchValue = null,
        $mode = null
    ) {
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

        if ($matchType) {
            $parameters['--match-type'] = $matchType;
        }

        if ($matchValue) {
            $parameters['--match-value'] = $matchValue;
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
            array(),
            array(null, 'sql'),
            array(null, 'php'),
            array('unit_test_generated'),
            array('unit_test_generated', 'sql'),
            array('unit_test_generated', 'php'),
            array('unit_test_generated_role', null, 'role', 'identifier', 'Anonymous'),
            array('unit_test_generated_role', null, 'role', 'identifier', 'Anonymous', 'create'),
            array('unit_test_generated_role', null, 'role', 'identifier', 'Anonymous', 'update'),
            array('unit_test_generated_content_type', 'yml', 'content_type', 'identifier', 'folder'),
            array('unit_test_generated_content_type', 'json', 'content_type', 'identifier', 'folder', 'update'),
            array('unit_test_generated_content_type', 'yml', 'content', 'contenttype_identifier', 'folder'),
            array('unit_test_generated_content_type', 'json', 'content', 'contenttype_identifier', 'folder', 'update')
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

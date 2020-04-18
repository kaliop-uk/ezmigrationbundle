<?php

include_once(__DIR__.'/CommandTest.php');

use Symfony\Component\Console\Input\ArrayInput;
use Kaliop\eZMigrationBundle\Tests\helper\BeforeStepExecutionListener;
use Kaliop\eZMigrationBundle\Tests\helper\StepExecutedListener;

/**
 * Tests the 'kaliop:migration:migrate' as well as the 'kaliop:migration:migration' command for eZTags
 */
class TagsTest extends CommandTest
{
    /**
     * @param string $filePath
     * @dataProvider goodDSLProvider
     */
    public function testExecuteGoodDSL($filePath = '')
    {
        $bundles = $this->getContainer()->getParameter('kernel.bundles');

        if ($filePath == '' || !isset($bundles['NetgenTagsBundle'])) {
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
        $this->assertGreaterThanOrEqual($count1 + 1, BeforeStepExecutionListener::getExecutions(), "Migration 'before step' listener did not fire");
        $this->assertGreaterThanOrEqual($count2 + 1, StepExecutedListener::getExecutions(), "Migration 'step executed' listener did not fire");

        $input = new ArrayInput(array('command' => 'kaliop:migration:migration', 'migration' => basename($filePath), '--delete' => true, '-n' => true));
        $exitCode = $this->app->run($input, $this->output);
        $output = $this->fetchOutput();
        $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
    }

    public function goodDSLProvider()
    {
        $dslDir = $this->dslDir.'/eztags';

        // try to make this work across phpunit versions, which run this before/after calling setUp()
        $container = $this->getContainer() == null ? $this->bootContainer() : $this->getContainer();
        $tagsFieldType = $container->get('ezpublish.fieldType.eztags');
        $settingsSchema = $tagsFieldType->getSettingsSchema();
        if (isset($settingsSchema['subTreeLimit'])) {
            $dslDir .= '/v2';
        } else {

            $dslDir .= '/v3';
        }

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
}

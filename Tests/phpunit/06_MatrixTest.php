<?php

include_once(__DIR__.'/MigrationExecutingTest.php');

use eZ\Publish\SPI\FieldType\ValidationError;
use Kaliop\eZMigrationBundle\Tests\helper\BeforeStepExecutionListener;
use Kaliop\eZMigrationBundle\Tests\helper\StepExecutedListener;

/**
* Tests the 'kaliop:migration:migrate' command for the (different versions of) Matrix field type
*/
class MatrixTest extends MigrationExecutingTest
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

        $this->prepareMigration($filePath);

        $count1 = BeforeStepExecutionListener::getExecutions();
        $count2 = StepExecutedListener::getExecutions();

        $output = $this->runCommand('kaliop:migration:migrate', array('--path' => array($filePath), '-n' => true, '-u' => true));
        // check that there are no notes after adding the migration
        $this->assertRegexp('?\| ' . basename($filePath) . ' +\| +\|?', $output);

        // simplistic check on the event listeners having fired off correctly
        $this->assertGreaterThanOrEqual($count1 + 1, BeforeStepExecutionListener::getExecutions(), "Migration 'before step' listener did not fire");
        $this->assertGreaterThanOrEqual($count2 + 1, StepExecutedListener::getExecutions(), "Migration 'step executed' listener did not fire");

        $this->deleteMigration($filePath);
    }

    public function goodDSLProvider()
    {
        $dslDir = $this->dslDir.'/ezmatrix';

        if (class_exists('EzSystems\MatrixBundle\FieldType\Matrix\Type')) {
            $ft = new EzSystems\MatrixBundle\FieldType\Matrix\Type();
            $errors = $ft->validateFieldSettings(array('columnList' => array()));
            if (count($errors)) {
                $dslDir .= '/community_01';
            } else {
                $dslDir .= '/community_02';
            }
        } else {
            if (class_exists('EzSystems\EzPlatformMatrixFieldtype\FieldType\Type')) {
                $dslDir .= '/platform';
            } else {
                return array(array(''));
            }
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

<?php

include_once(__DIR__.'/CommandTest.php');

use Kaliop\eZMigrationBundle\API\Collection\MigrationStepsCollection;
use Kaliop\eZMigrationBundle\API\Value\MigrationStep;

/**
 * Tests the 'filtering' features of the colections. Plain usage as array is already tested by th rest of the suite
 */
class CollectionsTest extends CommandTest
{
    public function testValidElements1()
    {
        $collection = new MigrationStepsCollection(array(new MigrationStep('test')));
    }

    public function testValidElements2()
    {
        $collection = new MigrationStepsCollection(array());
        $collection[] = new MigrationStep('test');
    }

    public function testValidElements3()
    {
        $collection = new MigrationStepsCollection(array());
        $collection['test'] = new MigrationStep('test');
    }

    public function testInvalidElements1()
    {
        $this->setExpectedException('InvalidArgumentException');
        $collection = new MigrationStepsCollection(array(1));
    }

    public function testInvalidElements2()
    {
        $collection = new MigrationStepsCollection(array());

        $this->setExpectedException('InvalidArgumentException');
        $collection[] = 'not a content';
    }

    public function testInvalidElements3()
    {
        $collection = new MigrationStepsCollection(array());

        $this->setExpectedException('InvalidArgumentException');
        $collection['test'] = true;
    }
}

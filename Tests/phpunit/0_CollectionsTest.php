<?php

include_once(__DIR__.'/CommandTest.php');

use Kaliop\eZMigrationBundle\API\Collection\ContentCollection;

/**
 * Tests the 'filtering' features of the colections. Plain usage as array is already tested by th rest of the suite
 */
class CollectionsTest extends CommandTest
{
    public function testInvalidElements1()
    {
        $this->setExpectedException('InvalidArgumentException');
        $collection = new ContentCollection(array(1));
    }

    public function testInvalidElements2()
    {
        $collection = new ContentCollection(array());

        $this->setExpectedException('InvalidArgumentException');
        $collection[] = 'not a content';
    }

    public function testInvalidElements3()
    {
        $collection = new ContentCollection(array());

        $this->setExpectedException('InvalidArgumentException');
        $collection['yo'] = true;
    }
}

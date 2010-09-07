<?php
require_once 'PHPUnit/Framework.php';

class HttpHelperTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $this->assertTrue(true, 'This should already work.');
 
        // Stop here and mark this test as incomplete.
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
    }
}
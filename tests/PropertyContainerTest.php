<?php
require_once 'NeoRestTestCase.php';

class PropertyContainerTest extends NeoRestTestCase
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
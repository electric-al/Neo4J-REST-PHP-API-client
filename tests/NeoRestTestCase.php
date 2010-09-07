<?php
require_once 'PHPUnit/Framework.php';
require_once 'NeoRest.php';

class NeoRestTestCase extends PHPUnit_Framework_TestCase
{
    public function setUp() {
        $this->graphDbUri = 'http://localhost:9999/';
        $this->graphDb = new GraphDatabaseService($this->graphDbUri);
        if (!$this->graphDb instanceof GraphDatabaseService) {
            $this->markTestIncomplete();
        }
    }
}
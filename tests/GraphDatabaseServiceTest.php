<?php
require_once 'PHPUnit/Framework.php';
require_once 'NeoRest.php';

class GraphDatabaseServiceTest extends PHPUnit_Framework_TestCase
{
    public function testException()
    {
        $uri = '';
        list($response, $http_code) = HttpHelper::jsonGetRequest($uri);
    }
}
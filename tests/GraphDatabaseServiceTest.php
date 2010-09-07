<?php
require_once 'NeoRestTestCase.php';
class GraphDatabaseServiceTest extends NeoRestTestCase
{
    public function testUrlSetting()
    {
        $this->assertEquals(
            $this->graphDbUri,
            $this->graphDb->getBaseUri(),
            'Hm, it seems like we can not get the URI back.'
        );
    }
}
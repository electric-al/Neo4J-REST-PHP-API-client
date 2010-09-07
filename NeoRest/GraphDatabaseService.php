<?php
/**
 * GraphDatabaseService abstracts a Neo4J database server.
 *
 * @package NeoRest
 */

/**
 * GraphDatabaseService abstracts a Neo4J database server.
 *
 * @example ../examples/demo.php Using the GraphDatabaseService
 *
 * @package NeoRest
 */
class GraphDatabaseService
{
	public $base_uri;
	
	/**
	 * HTTP utility helper
	 *
	 * @var HttpHelper
	 */
	protected $httpHelper;
	
	public function __construct($base_uri, $httpHelper=null)
	{
		$this->base_uri = $base_uri;
		
		if (!is_null($httpHelper)) {
		    $this->helper = $httpHelper;
		} else {
		    $this->helper = new HttpHelper;
		}
	}
	
	public function getNodeById($node_id)
	{
		$uri = $this->base_uri.'node/'.$node_id;
		
		list($response, $http_code) = $this->helper->jsonGetRequest($uri);

		switch ($http_code)
		{
			case 200:
				return Node::inflateFromResponse($this, $response);
			case 404:
				throw new NotFoundException();
			default:
				throw new NeoRestHttpException($http_code);
		}
	}
	
	public function createNode()
	{
		return new Node($this);
	}
	
	public function getBaseUri()
	{
		return $this->base_uri;
	}
}

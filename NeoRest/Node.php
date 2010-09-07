<?php
/**
 * Node in the graph.
 *
 * @package NeoRest
 */

/**
 * Node in the graph.
 *
 * @package NeoRest
 */
class Node extends PropertyContainer
{
	public $_neo_db;
	public $_id;
	public $_is_new;
	
	public function __construct(GraphDatabaseService $neo_db)
	{
		$this->_neo_db = $neo_db;
		$this->_is_new = TRUE;
	}
	
	public function delete()
	{
		if (!$this->_is_new) 
		{
			list($response, $http_code) = HttpHelper::deleteRequest($this->getUri());
			
			if ($http_code!=204) throw new HttpException($http_code);
			
			$this->_id = NULL;
			$this->_id_new = TRUE;
		}
	}
	
	public function save()
	{
		if ($this->_is_new) {
			list($response, $http_code) = HttpHelper::jsonPostRequest($this->getUri(), $this->_data);
			if ($http_code!=201) throw new HttpException($http_code);
		} else {
			list($response, $http_code) = HttpHelper::jsonPutRequest($this->getUri().'/properties', $this->_data);
			if ($http_code!=204) throw new HttpException($http_code);
		}

		if ($this->_is_new) 
		{
			$this->_id = end(explode("/", $response['self']));
			$this->_is_new=FALSE;
		}
	}
	
	public function getId()
	{
		return $this->_id;
	}
	
	public function isSaved()
	{
		return !$this->_is_new;
	}
	
	public function getRelationships($direction=Relationship::DIRECTION_BOTH, $types=NULL)
	{
		$uri = $this->getUri().'/relationships';
		
		switch($direction)
		{
			case Relationship::DIRECTION_IN:
				$uri .= '/in';
				break;
			case Relationship::DIRECTION_OUT:
				$uri .= '/out';
				break;
			default:
				$uri .= '/all';
		}
		
		if ($types)
		{
			if (is_array($types)) $types = implode("&", $types);
			
			$uri .= '/'.$types;
		}
		
		list($response, $http_code) = HttpHelper::jsonGetRequest($uri);
		
		$relationships = array();
		
		foreach($response as $result)
		{
			$relationships[] = Relationship::inflateFromResponse($this->_neo_db, $result);
		}
		
		return $relationships;
	}
	
	public function createRelationshipTo($node, $type)
	{
		$relationship = new Relationship($this->_neo_db, $this, $node, $type);
		return $relationship;
	}
	
	public function getUri()
	{
		$uri = $this->_neo_db->getBaseUri().'node';
	
		if (!$this->_is_new) $uri .= '/'.$this->getId();
	
		return $uri;
	}
	
	public static function inflateFromResponse(GraphDatabaseService $neo_db, $response)
	{
		$node = new Node($neo_db);
		$node->_is_new = FALSE;
		$node->_id = end(explode("/", $response['self']));
		$node->setProperties($response['data']);

		return $node;
	}
}

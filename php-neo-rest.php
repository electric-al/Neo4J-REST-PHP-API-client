<?php
/**
 * Neo4J REST PHP API client.
 * 
 * @package NeoRest
 */

//namespace NeoRest;

/**
 * GraphDatabaseService abstracts a Neo4J database server.
 *
 * @package NeoRest
 */
class GraphDatabaseService
{
	public $base_uri;
	
	public function __construct($base_uri)
	{
		$this->base_uri = $base_uri;
	}
	
	public function getNodeById($node_id)
	{
		$uri = $this->base_uri.'node/'.$node_id;
		
		list($response, $http_code) = HTTPUtil::jsonGetRequest($uri);
	
		switch ($http_code)
		{
			case 200:
				return Node::inflateFromResponse($this, $response);
			case 404:
				throw new NotFoundException();
			default:
				throw new HttpException($http_code);
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

/**
 * PropertyContainer is a simple data class.
 *
 * @package NeoRest
 */
class PropertyContainer
{
	public $_data;
	
	public function __set($k, $v)
	{
		if ($v===NULL && isset($this->_data[$k])) 
			unset($this->_data[$k]);
		else
			$this->_data[$k] = $v;
	}
	
	public function __get($k)
	{
		if (isset($this->_data[$k]))
			return $this->_data[$k];
		else
			return NULL;
	}
	
	public function setProperties($data)
	{
		$this->_data = $data;
	}
	
	public function getProperties()
	{
		return $this->_data;
	}
}

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
			list($response, $http_code) = HTTPUtil::deleteRequest($this->getUri());
			
			if ($http_code!=204) throw new HttpException($http_code);
			
			$this->_id = NULL;
			$this->_id_new = TRUE;
		}
	}
	
	public function save()
	{
		if ($this->_is_new) {
			list($response, $http_code) = HTTPUtil::jsonPostRequest($this->getUri(), $this->_data);
			if ($http_code!=201) throw new HttpException($http_code);
		} else {
			list($response, $http_code) = HTTPUtil::jsonPutRequest($this->getUri().'/properties', $this->_data);
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
		
		list($response, $http_code) = HTTPUtil::jsonGetRequest($uri);
		
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

/**
 * Relationship in the graph
 *
 * @package NeoRest
 */
class Relationship extends PropertyContainer
{
	const DIRECTION_BOTH 	= 'BOTH';
	const DIRECTION_IN 		= 'IN';
	const DIRECTION_OUT 	= 'OUT';
	
	public $_is_new;
	public $_neo_db;
	public $_id;
	public $_type;
	public $_node1;
	public $_node2;
	
	public function __construct(GraphDatabaseService $neo_db, $start_node, $end_node, $type)
	{
		$this->_neo_db = $neo_db;
		$this->_is_new = TRUE;
		$this->_type = $type;
		$this->_node1 = $start_node;
		$this->_node2 = $end_node;
	}
	
	public function getId()
	{
		return $this->_id;
	}
	
	public function isSaved()
	{
		return !$this->_is_new;
	}
	
	public function getType()
	{
		return $this->_type;		
	}
	
	public function isType($type)
	{
		return $this->_type==$type;
	}
	
	public function getStartNode()
	{
		return $this->_node1;
	}
	
	public function getEndNode()
	{
		return $this->_node2;
	}
	
	public function getOtherNode($node)
	{
		return ($this->_node1->getId()==$node->getId()) ? $this->getStartNode() : $this->getEndNode();
	}
	
	public function save()
	{
		if ($this->_is_new) {
			$payload = array(
				'to' => $this->getEndNode()->getUri(),
				'type' => $this->_type,
				'data'=>$this->_data
			);
			
			list($response, $http_code) = HTTPUtil::jsonPostRequest($this->getUri(), $payload);
			
			if ($http_code!=201) throw new HttpException($http_code);
		} else {
			list($response, $http_code) = HTTPUtil::jsonPutRequest($this->getUri().'/properties', $this->_data);
			if ($http_code!=204) throw new HttpException($http_code);
		}
				
		if ($this->_is_new) 
		{
			$this->_id = end(explode("/", $response['self']));
			$this->_is_new=FALSE;
		}
	}
	
	public function delete()
	{
		if (!$this->_is_new) 
		{
			list($response, $http_code) = HTTPUtil::deleteRequest($this->getUri());

			if ($http_code!=204) throw new HttpException($http_code);
			
			$this->_id = NULL;
			$this->_id_new = TRUE;
		}
	}
	
	public function getUri()
	{
		if ($this->_is_new)
			$uri = $this->getStartNode()->getUri().'/relationships';
		else
			$uri  = $this->_neo_db->getBaseUri().'relationship/'.$this->getId();
	
		//if (!$this->_is_new) $uri .= '/'.$this->getId();
	
		return $uri;
	}
	
	public static function inflateFromResponse(GraphDatabaseService $neo_db, $response)
	{
		$start_id = end(explode("/", $response['start']));
		$end_id = end(explode("/", $response['end']));

		$start = $neo_db->getNodeById($start_id);
		$end = $neo_db->getNodeById($end_id);
		
		$relationship = new Relationship($neo_db, $start, $end, $response['type']);
		$relationship->_is_new = FALSE;
		$relationship->_id = end(explode("/", $response['self']));
		$relationship->setProperties($response['data']);
		
		return $relationship;
	}
}

/**
 * HTTP-specific exception
 *
 * @package NeoRest
 */
class HttpException extends Exception
{
}

/**
 * HTTP 404 exception
 *
 * @package NeoRest
 */
class NotFoundException extends Exception
{
}


/**
 *	Very messy HTTP utility library
 */
class HTTPUtil 
{
	const GET = 'GET';
	const POST = 'POST';
	const PUT = 'PUT';
	const DELETE = 'DELETE';
	
	/**
	 * A general purpose HTTP request method
	 *
	 * @param string $url 
	 * @param string $method 
	 * @param string $post_data 
	 * @param string $content_type 
	 * @param string $accept_type 
	 *
	 * @return void
	 */
	function request($url, $method='GET', $post_data='', $content_type='', $accept_type='')
	{
		// Uncomment for debugging
		//echo 'HTTP: ', $method, " : " ,$url , " : ", $post_data, "\n";
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
	

		//if ($method==self::POST){
		//	curl_setopt($ch, CURLOPT_POST, true); 
		//} else {
		//	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		//}
		
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
	
		if ($post_data)
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			
			$headers = array(
						'Content-Length: ' . strlen($post_data),
						'Content-Type: '.$content_type,
						'Accept: '.$accept_type
						);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
		}
			
		$response = curl_exec($ch);

		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		return array($response, $http_code);
	}
	
	/**
	 * A HTTP request that returns json and optionally sends a json payload (post only)
	 *
	 * @param string $url
	 * @param string $method HTTP verb
	 * @param mixed $data 
	 *
	 * @return mixed
	 */
	function jsonRequest($url, $method, $data=NULL)
	{
		$json = json_encode($data);
		$ret = self::request($url, $method, $json, 'application/json', 'application/json');
		$ret[0] = json_decode($ret[0], TRUE);
		return $ret;
	}
	
	/**
	 * Convenience wrapper for making a PUT jsonRequest
	 *
	 * @param string $url 
	 * @param mixed $data
	 * 
	 * @see jsonRequest
	 *
	 * @return mixed
	 */
	function jsonPutRequest($url, $data)
	{
		return self::jsonRequest($url, self::PUT, $data);
	}
	
	/**
	 * Convenience wrapper for making a POST jsonRequest
	 *
	 * @param string $url 
	 * @param mixed $data
	 * 
	 * @see jsonRequest
	 *
	 * @return mixed
	 */
	function jsonPostRequest($url, $data)
	{
		return self::jsonRequest($url, self::POST, $data);
	}
	
	/**
	 * Convenience wrapper for making a GET jsonRequest
	 *
	 * @param string $url 
	 * @param mixed $data
	 * 
	 * @see jsonRequest
	 *
	 * @return mixed
	 */
	function jsonGetRequest($url)
	{
		return self::jsonRequest($url, self::GET);
	}
	
	/**
	 * Convenience wrapper for making a DELETE jsonRequest
	 *
	 * @param string $url 
	 * @param mixed $data
	 * 
	 * @see jsonRequest
	 *
	 * @return mixed
	 */
	function deleteRequest($url)
	{
		return self::request($url, self::DELETE);
	}
}
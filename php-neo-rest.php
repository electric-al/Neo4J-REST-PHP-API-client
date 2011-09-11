<?php

//namespace NeoRest;

class GraphDatabaseService
{
	var $base_uri;
	
	public function __construct($base_uri)
	{
		$this->base_uri = $base_uri.'db/data/';
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
	
	public function performCypherQuery($query, $inflate_nodes=true){ 
		$uri = $this->base_uri.'ext/CypherPlugin/graphdb/execute_query';
		$data = array('query'=>$query);
			
		list($response, $http_code) = HTTPUtil::jsonPostRequest($uri, $data);
				
		if ($inflate_nodes && $http_code==200) {
			// Process results to replace node object with actualy node objects
			
			for($i=0;$i<count($response['data']); $i++){
				for($j=0;$j<count($response['data'][$i]); $j++) {
					if (is_array($response['data'][$i][$j]) && isset($response['data'][$i][$j]['data'])) {
						$response['data'][$i][$j] = Node::inflateFromResponse($this, $response['data'][$i][$j]);
					}
				}
			}
		}		
				
		switch ($http_code)
		{
			case 200:
				return $response;
			case 404:
				throw new NotFoundException();
			default:
				throw new HttpException($http_code);
		}
	}
}

class PropertyContainer
{
	var $_data;
	
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

class Node extends PropertyContainer
{
	var $_neo_db;
	var $_id;
	var $_is_new;
	
	public function __construct($neo_db)
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
	
	public static function inflateFromResponse($neo_db, $response)
	{
		$node = new Node($neo_db);
		$node->_is_new = FALSE;
		$node->_id = end(explode("/", $response['self']));
		$node->setProperties($response['data']);

		return $node;
	}
}

class Relationship extends PropertyContainer
{
	const DIRECTION_BOTH 	= 'BOTH';
	const DIRECTION_IN 		= 'IN';
	const DIRECTION_OUT 	= 'OUT';
	
	var $_is_new;
	var $_neo_db;
	var $_id;
	var $_type;
	var $_node1;
	var $_node2;
	
	public function __construct($neo_db, $start_node, $end_node, $type)
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
	
	public static function inflateFromResponse($neo_db, $response)
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


class HttpException extends Exception
{
}

class NotFoundException extends Exception
{
}

class Traversal 
{
	/*const RETURN_TYPE_NODE = 'node';
	const RETURN_TYPE_RELATIONSHIP = 'relationship';
	const RETURN_TYPE_POSITION = 'position';
	const RETURN_TYPE_PATH = 'path';*/
	
	const RETURN_FILTER_ALL = 'all';
	const RETURN_FILTER_ALL_BUT_START_NODE = 'all but start node';
	
	const ORDER_DEPTH_FIRST = 'depth first';
	const ORDER_BREADTH_FIRST = 'breadth first';
	
	public function setOrder($order)
	{	
		$this->order = $order;
		return $this;
	}
	
	public function setMaxDepth($max_depth)
	{
		$this->max_depth = $max_depth;
		return $this;
	}
	
	public function setReturnFilter()
	{
	}
	
	public function setPruneEvaluator()
	{
	}
	
	public function setReturnType($return_type)
	{
	}
	
	public function getNodes()
	{
		
	}
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
	 *	A general purpose HTTP request method
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
	 *	A HTTP request that returns json and optionally sends a json payload (post only)
	 */
	function jsonRequest($url, $method, $data=NULL)
	{
		$json = json_encode($data);
		$ret = self::request($url, $method, $json, 'application/json', 'application/json');
		$ret[0] = json_decode($ret[0], TRUE);
		return $ret;
	}
	
	function jsonPutRequest($url, $data)
	{
		return self::jsonRequest($url, self::PUT, $data);
	}
	
	function jsonPostRequest($url, $data)
	{
		return self::jsonRequest($url, self::POST, $data);
	}
	
	function jsonGetRequest($url)
	{
		return self::jsonRequest($url, self::GET);
	}
	
	function deleteRequest($url)
	{
		return self::request($url, self::DELETE);
	}
}
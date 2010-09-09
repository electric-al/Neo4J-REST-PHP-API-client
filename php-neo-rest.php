<?php

//namespace NeoRest;

class GraphDatabaseService
{
	var $base_uri;
	
	public function __construct($base_uri)
	{
		$this->base_uri = $base_uri;
	}
	
	public function getNodeByUri($uri)
	{
		list($response, $http_code) = HTTPUtil::jsonGetRequest($uri);
	
		switch ($http_code)
		{
			case 200:
				break;
			case 404:
				throw new NotFoundException();
				break;
			default:
				throw new HttpException("http code: " . $http_code . ", response: " . print_r($response, true));
				break;
		}
		return Node::inflateFromResponse($this, $response);
	}
	
	public function getNodeById($node_id)
	{
		$uri = $this->base_uri.'node/'.$node_id;
		
		return $this->getNodeByUri($uri);
	}
	
	public function getRelationshipById($relationship_id)
	{
		$uri = $this->base_uri.'relationship/'.$relationship_id;
		
		return $this->getRelationshipByUri($uri);
	}

	public function getRelationshipByUri($uri)
	{
		list($response, $http_code) = HTTPUtil::jsonGetRequest($uri);
	
		switch ($http_code)
		{
			case 200:
				return Relationship::inflateFromResponse($this, $response);
			case 404:
				throw new NotFoundException();
			default:
				throw new HttpException("http code: " . $http_code . ", response: " . print_r($response, true));
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


// TODO Any reason not to change $_data to $properties and to make it public?
//      This would allow statements like the following:
//      $name = $node->properties['name'];

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
	var $_pathFinderData;
	
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
			
			if ($http_code!=204) throw new HttpException("http code: " . $http_code . ", response: " . print_r($response, true));
			
			$this->_id = NULL;
			$this->_id_new = TRUE;
		}
	}
	
	public function save()
	{
		if ($this->_is_new) {
			list($response, $http_code) = HTTPUtil::jsonPostRequest($this->getUri(), $this->_data);
			if ($http_code!=201) throw new HttpException("http code: " . $http_code . ", response: " . print_r($response, true));
		} else {
			list($response, $http_code) = HTTPUtil::jsonPutRequest($this->getUri().'/properties', $this->_data);
			if ($http_code!=204) throw new HttpException("http code: " . $http_code . ", response: " . print_r($response, true));
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
				$uri .= '/' . DIRECTION::INCOMING;
				break;
			case Relationship::DIRECTION_OUT:
				$uri .= '/' . DIRECTION::OUTGOING;
				break;
			default:
				$uri .= '/' . DIRECTION::BOTH;
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

// curl -H Accept:application/json -H Content-Type:application/json -d
// '{ "to": "http://localhost:9999/node/3" }'
// -X POST http://localhost:9999/node/1/pathfinder
// TODO Add handling for relationships
// TODO Add algorithm parameter
	public function findPaths(Node $toNode, $maxDepth=null, RelationshipDescription $relationships=null, $singlePath=null)
	{
		
		$this->_pathFinderData['to'] =  $this->_neo_db->getBaseUri().'node'.'/'.$toNode->getId();
		if ($maxDepth) $this->_pathFinderData['max depth'] = $maxDepth;
		if ($singlePath) $this->_pathFinderData['single path'] = $singlePath;
		if ($relationships) $this->_pathFinderData['relationships'] = $relationships->get();
		
		list($response, $http_code) = HTTPUtil::jsonPostRequest($this->getUri().'/pathfinder', $this->_pathFinderData);
		
		if ($http_code==404) throw new NotFoundException;
		if ($http_code!=200) throw new HttpException("http code: " . $http_code . ", response: " . print_r($response, true));
		
		$paths = array();
		foreach($response as $result)
		{
				$paths[] = Path::inflateFromResponse($this->_neo_db, $result);	
		}
		
		if (empty($paths)) {
			throw new NotFoundException();
		}
		
		return $paths;
	}	

	// Convenience method just returns the first path
	public function findPath(Node $toNode, $maxDepth=null, RelationshipDescription $relationships=null)
	{
		$paths = $this->findPaths($toNode, $maxDepth, $relationships, 'true');
		return $paths[0];
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
			
			if ($http_code!=201) throw new HttpException("http code: " . $http_code . ", response: " . print_r($response, true));
		} else {
			list($response, $http_code) = HTTPUtil::jsonPutRequest($this->getUri().'/properties', $this->_data);
			if ($http_code!=204) throw new HttpException("http code: " . $http_code . ", response: " . print_r($response, true));
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

			if ($http_code!=204) throw new HttpException("http code: " . $http_code . ", response: " . print_r($response, true));
			
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

class CurlException extends Exception
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

		// Batch jobs are overloading the local server so try twice, with a pause in the middle
		// TODO There must be a better way of handling this. What I've got below is an ugly hack.
		$count = 6;
		do {
			$count--;
			$response = curl_exec($ch);
			$error = curl_error($ch);
			if ($error != '') {
				echo "Curl got an error, sleeping for a moment before retrying: $count\n";
				sleep(10);
				$founderror = true;
			} else {
				$founderror = false;
			}
			
		} while ($count && $founderror);
	
		if ($error != '') {
			throw new CurlException($error);
		}

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
// print_r($json);		
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

class TraversalUniqueness 
{
	const NODE_GLOBAL = 'node global';
	const NODE_PATH = 'node path'; 
	const NODE_RECENT = 'node recent';
	const NONE = 'none';
	const RELATIONSHIP_GLOBAL = 'relationship global'; 
	const RELATIONSHIP_PATH = 'relationship path';
	const RELATIONSHIP_RECENT = 'relationship recent';
}


class TraversalDescription 
{
	const BREADTH_FIRST = 'breadth first';
	const DEPTH_FIRST = 'depth first';
	
	var $_neo_db;
	var $_traversalDescription;
	var $_order;
	var $_uniqueness;
	var $_relationships;
	var $_pruneEvaluator;
	var $_returnFilter;
	var $_data;
	var $_maxDepth;
	
	public function __construct($neo_db)
	{
		$this->_neo_db = $neo_db;
	}
			
	// Adds a relationship description.
	function relationships($type, $direction=NULL)
	{
		if ( $direction ) {
			$this->_relationships[] = array( 'type' => $type, 'direction' => $direction );
		} else {
			$this->_relationships[] = array( 'type' => $type );
		}
		
		$this->_traversalDescription['relationships'] = $this->_relationships;
	}
	
	function breadthFirst() {
		$this->_order = TraversalDescription::BREADTH_FIRST;
		$this->_traversalDescription['order'] = $this->_order;
	}
	
	function depthFirst() {
		$this->_order = TraversalDescription::DEPTH_FIRST;
		$this->_traversalDescription['order'] = $this->_order;
	}
	
	function prune($language, $body) {
		$this->_pruneEvaluator['language'] = $language;
		$this->_pruneEvaluator['body'] = $body;
		$this->_traversalDescription['prune evaluator'] = $this->_pruneEvaluator;
	}
	
	function returnFilter($language, $name) {
		$this->_returnFilter['language'] = $language;
		$this->_returnFilter['name'] = $name;
		$this->_traversalDescription['return filter'] = $this->_returnFilter;
	}
	
	function maxDepth($depth) {
		$this->_maxDepth = $depth;
		$this->_traversalDescription['max depth'] = $this->_maxDepth;
	}
	
	
	public function __invoke()
	{
		return $this->_traversalDescription;
	}
	
	public function traverse($node, $returnType) 
	{
		$this->_data = $this->_traversalDescription;
		$uri = $node->getUri().'/traverse'.'/'.$returnType;

// print_r($uri);
// print_r($traversalDescription);

		
		list($response, $http_code) = HTTPUtil::jsonPostRequest($uri, $this->_data);
		if ($http_code!=200) throw new HttpException($http_code);
		
		$objs = array();
		if ($returnType == TraversalType::NODE ) {
			$inflateClass = 'Node';
			$inflateFunc = 'inflateFromResponse';
		} elseif ($returnType == TraversalType::RELATIONSHIP) {
			$inflateClass = 'Relationship';
			$inflateFunc = 'inflateFromResponse';
		} else {
			$inflateClass = 'Path';
			$inflateFunc = 'inflateFromResponse';
		}
		
		foreach($response as $result)
		{
				$objs[] = $inflateClass::$inflateFunc($this->_neo_db, $result);	
//			$objs[] = $result;
		}
		
		return $objs;
	}	
	
	
}

// TODO Lazy evaluation?  There are a lot of requests to the REST
//      server and it's quite likely that not all the nodes 
//      or relationships in the path will be used.
class Path 
{
	
	var $_neo_db;
	var $_endNode;
	var $_startNode;
	var $_length;
	var $_nodes;
	var $_relationships;
	
	public function __construct($neo_db)
	{
		$this->_neo_db = $neo_db;
	}
		
	public function length()
	{
		return $this->_length;	
	}
	
	public function setLength($length)
	{
		$this->_length = $length;	
	}
	
	public function endNode()
	{
		return $this->_endNode;	
	}

	public function setEndNode($node)
	{
		$this->_endNode = $node;	
	}
	
	public function startNode()
	{
		return $this->_startNode;	
	}

	public function setStartNode($node)
	{
		$this->_startNode = $node;	
	}
	
	public function nodes()
	{
		return $this->_nodes;	
	}

	public function setNodes($arrayOfNodes)
	{
		$this->_nodes = $arrayOfNodes;
	}

	public function relationships()
	{
		return $this->_relationships;	
	}

	public function setRelationships($arrayOfRelationships)
	{
		$this->_relationships = $arrayOfRelationships;
	}
	
	public static function inflateFromResponse($neo_db, $response)
	{
		$path = new Path($neo_db);
		$path->setLength($response['length']);
		$path->setStartNode($neo_db->getNodebyUri($response['start']));
		$path->setEndNode($neo_db->getNodebyUri($response['end']));
		
		$nodes = array();
		
		foreach ($response['nodes'] as $nodeUri ) {
			$nodes[] = $neo_db->getNodeByUri($nodeUri);
		}
		$path->setNodes($nodes);

		$rels = array();
		
		foreach ($response['relationships'] as $relUri ) {
			$rels[] = $neo_db->getRelationshipByUri($relUri);
		}
		$path->setRelationships($rels);
		
		return $path;
	}
	
}

class Direction {
	const BOTH = 'all';
	const INCOMING = 'in';
	const OUTGOING = 'out'; 
}

class TraversalType {
	const NODE = 'node';
	const RELATIONSHIP = 'relationship';
	const PATH = 'path';
}


class IndexService {

	var $_neo_db;
	var $_uri;
	var $_data;
	
	public function __construct( GraphDatabaseService $neo_db)
	{
		$this->_neo_db = $neo_db;
	}
	
	public function index( Node $node, $key, $value ) {
		
		$this->_uri = $this->_neo_db->getBaseUri().'index/node/'.$key.'/'.$value;
		$this->_data = $node->getUri();

		list($response, $http_code) = HTTPUtil::request($this->_uri, HTTPUtil::POST, $this->_data );	
		if ($http_code!=201) throw new HttpException($http_code);
		
	}
	
	public function removeIndex(Node $node, $key, $value)
	{
		$this->_uri = $this->_neo_db->getBaseUri().'index/node/'.$key.'/'.$value.'/'.$node->getId();
		list($response, $http_code) = HTTPUtil::deleteRequest($this->_uri);
		if ($http_code!=204) throw new HttpException($http_code);
	}

	public function getNodes($key, $value ) {
		
		$this->_uri = $this->_neo_db->getBaseUri().'index/node/'.$key.'/'.$value;
		
		list($response, $http_code) = HTTPUtil::jsonGetRequest($this->_uri);
		if ($http_code!=200) throw new HttpException("http code: " . $http_code . ", response: " . print_r($response, true));
		$nodes = array();
		foreach($response as $nodeData) {
			$nodes[] = Node::inflateFromResponse( $this->_neo_db, $nodeData );
		}
		
		if (empty($nodes)) throw new NotFoundException();
		
		return $nodes;
		
	}
	
	// A hack for now.  The REST API doesn't offer an implementation of 
	// org.neo4j.index.IndexServe.getSingleNode();
	// So we just get the first element in the returned array.
	public function getNode($key, $value) {
		
		$nodes = $this->getNodes($key, $value);
				
		return $nodes[0];
		
	}
	
}

class RelationshipDescription {
	
	private  $_descriptions;
	
	function __construct( $type, $direction=null ) {
		if ( $direction ) {
			$this->_descriptions[] = array( 'type' => $type, 'direction' => $direction );
		} else {
			$this->_descriptions[] = array( 'type' => $type );
		}		
	}
	
	function add( $type, $direction=null ) {
		if ( $direction ) {
			$this->_descriptions[] = array( 'type' => $type, 'direction' => $direction );
		} else {
			$this->_descriptions[] = array( 'type' => $type );
		}		
	}
	
	function get()
	{
		return $this->_descriptions;
	}
}




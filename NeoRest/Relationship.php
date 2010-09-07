<?php
/**
 * Relationship in the graph
 *
 * @package NeoRest
 */

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
			
			list($response, $http_code) = HttpHelper::jsonPostRequest($this->getUri(), $payload);
			
			if ($http_code!=201) throw new NeoRestHttpException($http_code);
		} else {
			list($response, $http_code) = HttpHelper::jsonPutRequest($this->getUri().'/properties', $this->_data);
			if ($http_code!=204) throw new NeoRestHttpException($http_code);
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
			list($response, $http_code) = HttpHelper::deleteRequest($this->getUri());

			if ($http_code!=204) throw new NeoRestHttpException($http_code);
			
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

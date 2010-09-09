<?php
/**
 *	Test suite for php-neo-rest.
 *
 */
require('php-neo-rest.php');

assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_WARNING, 1);

/**
 *	Create a graphDb connection 
 *	Note:	this does not actually perform any network access, 
 *			the server is only accessed when you use the database
 */
$graphDb = new GraphDatabaseService('http://localhost:9999/');

/** 
 * Create a node, save it, and then verify it's there.
 * 
 */

$firstNode = $graphDb->createNode();
$firstNode->name = 'First Node';
$firstNode->save();
$firstNodeId = $firstNode->getId();

$firstAgain = $graphDb->getNodeById($firstNodeId);
assert('$firstAgain->name == \'First Node\' /* Retrieved node name should have a name of \'First Node\' */');

/** 
 * Delete a node and make sure it's not there anymore
 * 
 */
$firstNode->delete();

try {
	$firstAgain = $graphDb->getNodeById($firstNodeId);
}
catch (NotFoundException $e) {
	assert('true /* Caught NotFoundException on deleted node */');
}
catch (Exception $e) {
	assert('false /* Caught unexpected exception on deleted node */');
}

/** 
 * Create a relationship and retrieve it.
 * 
 */

$firstNode = $graphDb->createNode();
$firstNode->name = 'First Node';
$firstNode->save();

$secondNode = $graphDb->createNode();
$secondNode->name = 'Second Node';
$secondNode->save();

$rel = $firstNode->createRelationshipTo($secondNode, 'owns');
$rel->day = 'Monday';
$rel->save();
$relId = $rel->getId();

$relAgain = $graphDb->getRelationshipById($relId);
assert('$relAgain->day == \'Monday\' /* Property \'day\' of the relationship should be \'Monday\' */');


/*
 * Traverse the nodes, traversal type of path
 * 
 */

$td = new TraversalDescription($graphDb);
$td->depthFirst();
$paths = $td->traverse($firstNode, TraversalType::PATH );
$path = $paths[0];

$len = $path->length();
assert('$len == 1 /* Length of path should be 1 */');

$startNode = $path->startNode();
assert('$startNode->getId() == $firstNode->getId() /* First Node should be start node */');

$endNode = $path->endNode();
assert('$endNode->getId() == $secondNode->getId() /* Second Node should be end node */');

$pathRels = $path->relationships();
$pathRel = $pathRels[0];

assert('$pathRel->getId() == $rel->getId() /* Traversed relationship should be the same a created relationship */');

/*
 * Traverse the nodes, traversal type of node
 * 
 */

$td = new TraversalDescription($graphDb);
$td->depthFirst();
$nodes = $td->traverse($firstNode, TraversalType::NODE );

assert('sizeof($nodes) == 1 /* Size of returned nodes array should be 2 */');

$node = $nodes[0];
assert('$node->getId() == $secondNode->getId() /* only node should be second node */');


/*
 * Traverse the nodes, traversal type of node with return filter of all which will
 * include the origin node.
 * 
 */

$td = new TraversalDescription($graphDb);
$td->depthFirst();
$td->returnFilter('builtin', 'all');
$nodes = $td->traverse($firstNode, TraversalType::NODE );

assert('sizeof($nodes) == 2 /* Size of returned nodes array should be 2 */');

$node = $nodes[0];
assert('$node->getId() == $firstNode->getId() /* nodes[0] should be equal to first node */');

$node = $nodes[1];
assert('$node->getId() == $secondNode->getId() /* nodes[1] should be equal to second node */');


/*
 * Traverse the nodes, traversal type of relationship.
 * 
 */
assert('false /* Traversal type of relationship - Not implemented yet */');


/*
 * Traverse the nodes, complex traversal with all features
 * 
 */
assert('false /* Complex traversal with all features - Not implemented yet */');


/* 
 * Delete the relationship and make sure it can't be retreived.
 * 
 */
$rel->delete();


try {
	$relAgain = $graphDb->getRelationshipById($relId);
}
catch (NotFoundException $e) {
	assert('true /* Caught NotFoundException on deleted relationship */');
}
catch (Exception $e) {
	assert('false /* Caught unexpected exception on deleted relationship */');
}


/* 
 * Clean up and delete the nodes
 * 
 */
$firstNode->delete();
$secondNode->delete();

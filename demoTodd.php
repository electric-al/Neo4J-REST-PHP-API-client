<?php
/**
 *	Include the API PHP file
 */
require('php-neo-rest.php');

/**
 *	Create a graphDb connection 
 *	Note:	this does not actually perform any network access, 
 *			the server is only accessed when you use the database
 */
$graphDb = new GraphDatabaseService('http://localhost:9999/');

/**
 *	Try the traversal.
 */
$firstNode = $graphDb->getNodeById(1);
$secondNode = $graphDb->getNodeById(2);


$td = new TraversalDescription($graphDb);
$td->depthFirst();
$paths = $td->traverse($firstNode, TraversalType::PATH );
print_r($paths);
echo "\n";

$nodes = $td->traverse($firstNode, TraversalType::NODE );
print_r($nodes);
echo "\n";

$relationships = $td->traverse($firstNode, TraversalType::RELATIONSHIP );
print_r($relationships);
echo "\n";

// Try all of the features of the Traversal desc.
$td->relationships('KNOWS');
$td->relationships('LOVES');
$td->maxDepth(3);
$td->prune('javascript', "position.endNode().getProperty('message')=='third node';");
$td->returnFilter('builtin', 'all');
$nodes = $td->traverse($firstNode, TraversalType::NODE );
print_r($nodes);
echo "\n";

exit(0);


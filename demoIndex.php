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
 *	Find and print nodes with specific index.
 */
$indexService = new IndexService( $graphDb );

echo "Getting first node\n";
$firstNode = $graphDb->getNodeById(1);
echo "Got first node\n";

echo "Creating first index\n";
$indexService->index($firstNode, 'fbuid', '12345');
echo "Created first index\n";

$nodes = $indexService->getNodes('fbuid', '12345');
print_r($nodes);
echo "\n";

echo "Removing first index\n";
$indexService->removeIndex($firstNode, 'fbuid', '12345');
echo "Removed first index\n";

// What happens when no nodes are found?
try {
	$nodes = $indexService->getNodes('fbuid', 'X12345X');
	print_r($nodes);
	echo "\n";
}
catch (NotFoundException $e) {
	echo "No nodes were found for the index key and value.\n";
}

exit(0);

$nodes = $indexService->getNodes('fbuid', '12345');
if ($nodes) {
	foreach ($nodes as $node) {
		$indexService->removeIndex($node, 'fbuid', '12345');
	}
}

$firstNode = $graphDb->getNodeById(1);
$secondNode = $graphDb->getNodeById(2);

$indexService->index($firstNode, 'fbuid', '12345');
$indexService->index($secondNode, 'fbuid', '12345');




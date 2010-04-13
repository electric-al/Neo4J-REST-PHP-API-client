<?php

require('php-neo-rest.php');

$graphDb = new GraphDatabaseService('http://localhost:9999/');

$firstNode = $graphDb->createNode();
$secondNode = $graphDb->createNode();
$thirdNode = $graphDb->createNode();

$firstNode->message = "Hello, ";
$firstNode->save();

$secondNode->message = "world!";
$secondNode->save();

$thirdNode->message = "third node";
$thirdNode->save();

$relationship = $firstNode->createRelationshipTo($secondNode, 'KNOWS');
$relationship->message = "brave Neo4j";
$relationship->save();

$relationship2 = $thirdNode->createRelationshipTo($secondNode, 'LOVES');
$relationship2->save();


dump_node($firstNode);
dump_node($secondNode);
dump_node($thirdNode);


function dump_node($node)
{
	$rels = $node->getRelationships();
	
	echo 'Node '.$node->getId()."\t\t\t\t\t\t\t\t".json_encode($node->getProperties())."\n";
	
	foreach($rels as $rel)
	{
		$start = $rel->getStartNode();
		$end = $rel->getEndNode();
		
		echo 	"  Relationship ".$rel->getId()."  :  Node ".$start->getId()." ---".$rel->getType()."---> Node ".$end->getId(),
				"\t\t\t\t\t\t\t\t".json_encode($rel->getProperties())."\n";
	}
}
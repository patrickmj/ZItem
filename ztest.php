<?php

require_once('phpZoteroEntries.php');
require_once('../phpZotero/phpZotero.php');


$userId = '34550';
$apiKey = 'xIB4sMwaZkDXj3QpEstjndGy';

$z = new phpZotero($userId, $apiKey);

$xml = $z->getUserItems(array('limit'=>10));

//echo $xml->saveXML();
$es = new phpZoteroEntries($xml);
//echo count($es->entries);

header('Content-type: application/json');
echo $es->getRdf('rdf/json');
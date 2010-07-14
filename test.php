<?php

require_once('config.php');
require_once(PHP_ZOTERO_PATH);
require_once(PHP_ZOTERO_ENTRIES_PATH);

$z = new phpZotero($zoteroUserName, $zoteroUserKey);
$feed = $z->getUserItems(array('start'=>0));
$ze = new phpZoteroEntries($feed);
//print_r( $ze->getUris() );
//print_r( $ze->getTypes() );
//print_r( $ze->getEntriesGroupedByType() );
print_r( $ze->getEntriesByType('webpage', true));
//header('Content-type: application/json');
//echo $ze->getEntriesAsJSON();
//echo $ze->getRdf('rdf/json');




?>

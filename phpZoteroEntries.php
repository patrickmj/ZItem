<?php

require_once('phpZoteroEntry.php');

class phpZoteroEntries {
	
    public $entries = array();
    public $arcConf; 
  
    public function __construct($feed = false) {
      if ($feed) {
      	$this->addEntriesFromFeed($feed);
      }
      
      $this->arcConf = array(
        'ns' => array(
          'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
          'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
          'bibo' => 'http://http://purl.org/ontology/bibo/',
          'dcterms' => 'http://purl.org/dc/terms/',
          'po' => 'http://purl.org/ontology/po/',
          'doap' => 'http://usefulinc.com/ns/doap#',
          'sioct' => 'http://rdfs.org/sioc/types#',
          'sc' => 'http://umbel.org/umbel/sc/',
          'address' => 'http://schemas.talis.com/2005/address/schema#',
          'marcrel' => 'http://id.loc.gov/vocabulary/relators',
          'z' => 'http://www.zotero.org/namespaces/export#'
        )
      );     
    }  
    
    
    public function addEntriesFromFeed($feed) {
      
      if (is_string($feed)) {
        $dom = new DOMDocument();
        //cleanup GET param separators in the links in the feed
        $feed = str_replace('&', '&amp;', $feed);
        $dom->loadXML($feed);
      } else if( get_class($feed) == 'DOMDocument' ) {        
        $dom = $feed;
        //$newFeedNode = $dom->importNode($feed, true);
        //$dom->appendChild($newFeedNode);
      } else {
        throw new Exception('Entry must be either an XML string or an ATOM feed DOMNode');        
      }     
      
      $xpath = new DOMXPath($dom);
      $xpath->registerNamespace('zxfer', 'http://zotero.org/ns/transfer');
      $xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
      $entryNodes = $xpath->query('//atom:entry');
      for($i=0 ; $i<$entryNodes->length; $i++) {
        $newEntry =  new phpZoteroEntry($entryNodes->item($i));
        $this->entries[$newEntry->itemUri] = $newEntry;
      }          	
    }
    
    public function addEntry($entry) {
        $newEntry =  new phpZoteroEntry($entry);
        $this->entries[$newEntry->itemUri] = $newEntry;
    }
    
    public function getEntries($flatten = false) {
    	if($flatten) {
    		return array_values($this->entries);
    	}
      return $this->entries;
    }
    
    public function getEntriesByType($type, $flatten = false) {
    	$retArray = array();
      foreach($this->entries as $entry) {
    		if($entry->itemType == $$type) {
    			if($flatten) {
    				$retArray[] = $entry;
    			} else {
    				$retArray[$entry->itemUri] = $entry;
    			}
    		}
    	}
      return $retArray;
    }
    
    public function getEntriesByFieldValue($field, $value, $flatten = false) {
    	$retArray = array();
      foreach($this->entries as $entry) {
      	if($entry->getFieldValue($field) == $value) {
          if($flatten) {
            $retArray[] = $entry;
          } else {
            $retArray[$entry->itemUri] = $entry;
          }      		
      	}
      }
      return $retArray;
    }
    
    public function getEntryByUri($uri) {
    	return $this->entries[$uri];
    }
    
    public function getEntriesGroupedByType($flatten = false) {
    	$retArray = array();
      foreach($this->entries as $entry) {
      	if (! isset($retArray[$entry->itemType])) {
      		$retArray[$entry->itemType] = array();
      	}
        if($flatten) {
        	$retArray[$entry->itemType][] = $entry;
        } else {
          $retArray[$entry->itemType][$entry->itemURI] = $entry;	
        }        
      }
    }
    
    public function getEntriesAsJson($flatten = false) {
      $retArray = array();
      foreach($this->entries as $entry) {
      	if($flatten) {
      		$retArray[] = $entry->getEntryAsJson(false);
      	} else {
      		$retArray[$entry->itemUri] = $entry->getEntryAsJson(false);
      	}
      }
      if($flatten) {
      	return json_encode($retArray);
      }
      return json_encode($retArray, true);
    }
    
    public function getUris() {
    	return array_keys($this->entries);
    }
    
    public function getRdf($format = 'rdf/xml') {
      if( ! defined('PHP_ZOTERO_ENTRIES_ARC_PATH') ) {
        throw new Exception('PHP_ZOTERO_ENTRIES_ARC_PATH must be defined and valid to use RDF methods');
      }
      switch($format) {
        case 'rdf/xml':
          $ser = ARC2::getRDFXMLSerializer($this->arcConf);           
        break;
      
        case 'rdf/json':
          $ser = ARC2::getRDFJsonSerializer($this->arcConf);            
        break;
      
        case 'turtle':
          $ser = ARC2::getTurtleSerializer($this->arcConf);
        break;
      
        case 'ntriples':
          $ser = ARC2::getNTriplesSerializer($this->arcConf);            
        break;      
      }        
      return $ser->getSerializedIndex($this->getArcIndex());
    }
    
    public function getArcIndex() {
      if( ! defined('PHP_ZOTERO_ENTRIES_ARC_PATH') ) {
      	throw new Exception('PHP_ZOTERO_ENTRIES_ARC_PATH must be defined and valid to use RDF methods');
      }      
    	$arcIndex = array();
      foreach($this->entries as $entry) {
      	$arcIndex = array_merge($arcIndex, $entry->getArcIndex() );
      }
      return $arcIndex;
    }
    
    public function getNSMapJson() {
    	return json_encode($this->arcConf['ns'], true);
    }
}


<?php

require_once('phpZoteroEntry.php');

class phpZoteroEntries {
	
    /**
     * $entries An array of the zotero entries, hashed by their URI
     */
    public $entries = array();
    
    /**
     * $arcConf a configuration array for ARC2 (e.g., namespace declarations)
     */    
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
    
    /**
     * addEntriesFromFeed adds all the entries from a zotero api feed to the collection
     * @param $feed either a DOMDocument or string xml of the feed
     */
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
    
    /**
     * addEntry adds a single entry from a zotero api feed to the collection
     * @param $entry either a DOMNode or an xml string of the node
     */
    
    public function addEntry($entry) {
        $newEntry =  new phpZoteroEntry($entry);
        $this->entries[$newEntry->itemUri] = $newEntry;
    }
    
    /**
     * getEntries get all the entries for the collection
     * @param $flatten whether to flatten the URI hash to just the values (entries) 
     * @return array the array of entries, either URI-hashed or flattened
     */
    
    public function getEntries($flatten = false) {
    	if($flatten) {
    		return array_values($this->entries);
    	}
      return $this->entries;
    }
    
    
    /**
     * getEntriesByType get all the entries of a particular zotero type
     * @param $type the type
     * @param $flatten whether to flatten the URI hash to just the values (entries) 
     * @return array the array of entries, either URI-hashed or flattened 
     */
    public function getEntriesByType($type, $flatten = false) {
    	$retArray = array();
      foreach($this->entries as $entry) {
    		if($entry->itemType == $type) {
    			if($flatten) {
    				$retArray[] = $entry;
    			} else {
    				$retArray[$entry->itemUri] = $entry;
    			}
    		}
    	}
      return $retArray;
    }
    
    /**
     * getTypes get all the types of items in the collection
     * @return array the zotero types
     */
    public function getTypes() {
    	$retArray = array();
      foreach($this->entries as $uri=>$entry) {
      	$retArray[] = $entry->getItemType();
      }
      return array_unique($retArray);
    }
    
    /**
     * getEntriesByFieldValue get the entries with a particular field-value pair
     * @param $field the field
     * @param $value the value
     * @param $flatten whether to flatten the URI hash to just the values (entries) 
     * @return array the array of entries, either URI-hashed or flattened
     */
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
    
    /**
     * getEntryByUri get the entry with a particular URI
     * @param $uri the uri
     * @return phpZoteroEntry the entry
     */
    
    public function getEntryByUri($uri) {
    	return $this->entries[$uri];
    }
    
    /**
     * getEntriesGroupsByType return an array with the entries grouped by type
     * @param $flatten whether to flatten the URI hash to just the values (entries) 
     * @return array the array of entries, either URI-hashed or flattened, nested in an array hashed by type
     */
    
    public function getEntriesGroupedByType($flatten = false) {
    	$retArray = array();
      foreach($this->entries as $entry) {
      	if (! isset($retArray[$entry->itemType])) {
      		$retArray[$entry->itemType] = array();
      	}
        if($flatten) {
        	$retArray[$entry->itemType][] = $entry;
        } else {
          $retArray[$entry->itemType][$entry->itemUri] = $entry;	
        }        
      }
      return $retArray;
    }
    
    /**
     * getEntriesAsJson get all the entries as a JSON object
     * @param $flatten whether to flatten the URI hash to just the values (entries) 
     * @return string json encoded string of the entries
     */
    
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
    /**
     * getRdf get the full BIBO RDF serialization of the collection of entries
     * @param $format the serialization format
     * @throws Exception if PHP_ZOTERO_ENTRIES_ARC_PATH is not defined
     */
     
    public function getRdf($format = 'rdf/xml') {
      if( ! defined('PHP_ZOTERO_ENTRIES_ARC_PATH') ) {
        throw new Exception('PHP_ZOTERO_ENTRIES_ARC_PATH must be defined and valid to use RDF methods');
      }
      require_once(PHP_ZOTERO_ENTRIES_ARC_PATH);
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
    
    /**
     * getArcIndex returns the ARC2 Index structure for the collection
     * @throws Exception if PHP_ZOTERO_ENTRIES_ARC_PATH is not defined
     * @return array ARC2 Index structure
     */
     
    public function getArcIndex() {
      if( ! defined('PHP_ZOTERO_ENTRIES_ARC_PATH') ) {
      	throw new Exception('PHP_ZOTERO_ENTRIES_ARC_PATH must be defined and valid to use RDF methods');
      }
      require_once(PHP_ZOTERO_ENTRIES_ARC_PATH);      
    	$arcIndex = array();
      foreach($this->entries as $entry) {
      	$arcIndex = array_merge($arcIndex, $entry->getArcIndex() );
      }
      return $arcIndex;
    }

    /**
     * getNSMapJson get the namespace map for ARC2 structures as JSON
     * @@return string json encoded object
     */
    
    public function getNSMapJson() {
    	return json_encode($this->arcConf['ns'], true);
    }
}


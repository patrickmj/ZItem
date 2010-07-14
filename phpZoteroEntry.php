<?php


/**
 * @class phpZoteroEntry a class for processing an individual Atom Entry from the Zotero API feed
 */

class phpZoteroEntry {

    public $contentType;
    public $dom;
    public $xpath;
    public $author;
    public $itemUri;
    public $fields;
    public $itemType;
    public $dateAdded;
    public $dateModified;
    
    /**
     * $arcConf a configuration array for ARC2 (e.g., namespace declarations)
     */
    public $arcConf;
    public $arcIndex = false; // an array in ARC2's index structure
    
    
    /**
     * __construct
     * @param $xml an xml structure for the Atom Entry (string or DOMNode)
     */
    
    public function __construct($xml) {
      $this->dom = new DOMDocument();
      if (is_string($xml)) {
        $this->dom->loadXML($xml);
      } else if( (get_class($xml) == 'DOMElement') && $xml->nodeName == 'entry') {        
        $newEntryNode = $this->dom->importNode($xml, true);
        $this->dom->appendChild($newEntryNode);
      } else {
        throw new Exception('Entry must be either an XML string or an ATOM entry DOMNode');        
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
      
      $this->xpath = new DOMXPath($this->dom);
      $this->xpath->registerNamespace('zapi', 'http://zotero.org/ns/api');
      $this->xpath->registerNamespace('zxfer', 'http://zotero.org/ns/transfer');
      $this->xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
      $this->xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');
      //$this->itemType = $this->xpath->query('//zxfer:item/@itemType')->item(0)->textContent;
      $this->itemType = $this->xpath->query('//zapi:itemType')->item(0)->textContent;
      //$this->dateAdded = $this->xpath->query('//zxfer:item/@dateAdded')->item(0)->textContent;
      $this->dateAdded = $this->xpath->query('//atom:published')->item(0)->textContent;
      //$this->dateModified = $this->xpath->query('//zxfer:item/@dateModified')->item(0)->textContent;
      $this->dateModified = $this->xpath->query('//atom:updated')->item(0)->textContent;
      $this->contentType = $this->xpath->query('//atom:content/@type')->item(0)->textContent;
         
      $this->setItemUri();
      $this->setFields();           
      $this->setAuthor();
      
      //done with the dom and the xpath now, so unset them to free up some resources
      unset($this->dom);
      unset($this->xpath);
    }
    
    /**
     * getFields get the fields array for the zotero item
     * @return array the fields
     */
        
    public function getFields() {
    	return $this->fields;
    }

    /**
     * getNSMapJson get the namespace map for ARC2 structures as JSON
     * @@return string json encoded object
     */

    public function getNSMapJson() {
      return json_encode($this->arcConf['ns'], true);
    }
    
    /**
     * getFieldValue get the value for a field on the zotero item
     * @param $field the field name
     * @return string the value
     */
    public function getFieldValue($field) {
    	if($$this->fieldExists($field)) {
    		return $this->fields[$field];
    	}
      return false;
    }
    
    public function getItemType() {
    	return $this->itemType;
    }
    
    public function getDateAdded() {
    	return $this->dateAdded;
    }
    
    public function getDateModified() {
      return $this->dateModified;	
    }
    
    /**
     * getFieldsAsJson get all the item's fields as JSON
     * @return string json encoded object
     */
    
    public function getFieldsAsJson() {
    	return json_encode($this->fields, true);
    }
    
    /**
     * getItemAsJson get the item as json
     * @param $encode default = true. whether to json_encode the PHP object
     * @return mixed either a json encoded string, or a PHP stdClass object 
     */
    public function getItemAsJson($encode = true) {
    	$itemObj = new StdClass();
      $itemObj->uri = $this->itemUri;
      $itemObj->itemType = $this->itemType;
      $itemObj->dateAdded = $this->dateAdded;
      $itemObj->dateModified = $this->dateModified;
      $itemObj->fields = $this->fields;
      if($encode) {
        return json_encode($itemObj, true);	
      }
      return $itemObj;
    }
    
    /**
     * getEntryAsJson get the complete entry as json
     * @param $encode default = true. whether to json_encode the PHP object
     * @return mixed either a json encoded string, or a PHP stdClass object  
     */
     
    public function getEntryAsJson($encode = true) {
    	$entryObj = new StdClass();
      $entryObj->itemUri = $this->itemUri;
      $entryObj->item = $this->getItemAsJson(false);
      $entryObj->author = $this->author;
      if($encode) {
        return json_encode($entryObj, true);
      }
      return $entryObj;
    }
    /**
     * fieldExists check whether a field exists for the zotero item
     * @param $field the field
     * @return boolean 
     */
    public function fieldExists($field) {
    	return array_key_exists($field, $this->fields);
    }
    
    /**
     * getArcIndex return an index in ARC2's index structure.
     * @throws Exception if PHP_ZOTERO_ENTRIES_ARC_PATH is not defined
     * @return array
     */
    
    public function getArcIndex() {
      if( ! defined('PHP_ZOTERO_ENTRIES_ARC_PATH') ) {
        throw new Exception('PHP_ZOTERO_ENTRIES_ARC_PATH must be defined and valid to use RDF methods');
      }        
      if(! $this->arcIndex) {
        $this->setRdf();
      }
      return $this->arcIndex;    	
    }
    
    /**
     * getEntryAsRdf returns the BIBO RDF for the entire Entry
     * @param $format default 'rdf/xml' the rdf serialization to use
     * @return string the RDF serialization
     */
    
    public function getEntryAsRdf($format = 'rdf/xml') {
      if( ! defined('PHP_ZOTERO_ENTRIES_ARC_PATH') ) {
        throw new Exception('PHP_ZOTERO_ENTRIES_ARC_PATH must be defined and valid to use RDF methods');
      }
        
      if(! $this->arcIndex) {
        $this->setRdf();
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
      return $ser->getSerializedIndex($this->arcIndex);    	
    }
    
    
    /**
     * getItemAsRdf returns the BIBO RDF for the entire Zotero Item
     * @param $format default 'rdf/xml' the rdf serialization to use
     * @return string the RDF serialization
     * @throw Exception if PHP_ZOTERO_ENTRIES_ARC_PATH is not defined
     */
        
    public function getItemAsRdf($format = 'rdf/xml') {

      if( ! defined('PHP_ZOTERO_ENTRIES_ARC_PATH') ) {
        throw new Exception('PHP_ZOTERO_ENTRIES_ARC_PATH must be defined and valid to use RDF methods');
      }
        
      if(! $this->arcIndex) {
      	$this->setRdf();
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
      return $ser->getSerializedIndex(array($this->itemUri=>$this->arcIndex[$this->itemUri]));
    }
    
    /**
     * setRDF sets the ARC2 Index for the entry
     * @throw Exception if PHP_ZOTERO_ENTRIES_ARC_PATH is not defined
     */
    public function setRdf() {
      if( ! defined('PHP_ZOTERO_ENTRIES_ARC_PATH') ) {
        throw new Exception('PHP_ZOTERO_ENTRIES_ARC_PATH must be defined and valid to use RDF methods');
      }
      require_once(PHP_ZOTERO_ENTRIES_ARC_PATH);        
      $this->arcIndex = array();
      $this->arcIndex[$this->itemUri] = array();
      $props = array();
      $props = $this->filterItemType($props);
      $props = $this->filterFields($props);
      //$props = $this->filterCreatorInfo($props); TODO
      $this->arcIndex[$this->itemUri] = $props;
    }
    
    private function mintUri($type, $prop) {
      $slug = md5($this->itemUri);   
      
      $typeExplode = explode(':', $type);
      $typeStr = strtolower($typeExplode[1]);
      $propExplode = explode(':', $prop);
      
      $propStr = strtolower($propExplode[1]);
      return PHP_ZOTERO_ENTRIES_BASE_URI . "/$propStr/$typeStr-$slug";        
    }
    
    /**
     * buildTripleChain handles the cases in which a Zotero field requires an intermediary RDF subject in
     * the BIBO mapping.
     */
    
    private function buildTripleChain($value, $prop1, $prop2, $typesArray, $props) {
      $subjectURI = $this->mintUri($typesArray[0], $prop1);
    	if(! array_key_exists( $subjectURI, $this->arcIndex)) {
    		$this->arcIndex[$subjectURI] = array();
    	}
      if(! array_key_exists($prop2, $this->arcIndex[$subjectURI])) {
        $this->arcIndex[$subjectURI][$prop2] = array();
      }        

      $this->arcIndex[$subjectURI][$prop2][] = array('value'=>$value, 'type'=>'literal');
      foreach($typesArray as $type) {
        $this->arcIndex[$subjectURI]['rdf:type'][] = array('value'=>$type, 'type'=>'uri');      	
      }
      if(! array_key_exists($prop1, $props) ) {
      	$props[$prop1] = array();
      }
      $props[$prop1][] = array('value'=>$subjectURI, 'type'=>'uri');
      return $props;  
    }
    
    protected function setFields() {
      if($this->contentType == 'xhtml') {
      	$query = '//atom:content//xhtml:tr';
        $fields = $this->xpath->query($query);
        for($i=0; $i < $fields->length; $i++) {
          $fieldName = $fields->item($i)->getAttribute('class');
          $this->fields[$fieldName] = $fields->item($i)->getElementsByTagName('td')->item(0)->textContent;
        }
      }
      
      if($this->contentType == 'application/xml') {
      	$query = '//zxfer:field';
        $fields = $this->xpath->query($query);
        for($i=0; $i < $fields->length; $i++) {
          $fieldName = $fields->item($i)->getAttribute('name');
          $this->fields[$fieldName] = $fields->item($i)->textContent;
        }         
      }
    
    }

    protected function setAuthor() {
    	$this->author = array();
      $this->author['name'] = $this->xpath->query('//atom:author/atom:name')->item(0)->textContent;
      $this->author['uri'] = $this->xpath->query('//atom:author/atom:uri')->item(0)->textContent;  
    }
    protected function setItemUri() {
    	
      $id = $this->xpath->query('//atom:id')->item(0);
      $split = explode('?', $id->textContent);
      $this->itemUri = $split[0];
      
      
    }
    

    private function filterItemType($props) {

      switch($this->itemType) {
        case 'artwork':
          $props['rdf:type'][] = array('value'=>'bibo:Image', 'type'=>'uri');        
        break;
        
        case 'attachment':
          $props['rdf:type'][] = array('value'=>'z:Attachment', 'type'=>'uri');
        break;
                
        case 'audioRecording':
          $props['rdf:type'][] = array('value'=>'bibo:AudioDocument', 'type'=>'uri');        
        break;
        
        case 'bill':
          $props['rdf:type'][] = array('value'=>'bibo:Bill', 'type'=>'uri');        
        break;
        
        case 'blogPost':
          $props['rdf:type'][] = array('value'=>'bibo:Article', 'type'=>'uri');
          $props['rdf:type'][] = array('value'=>'sioct:BlogPost', 'type'=>'uri');
        break;
        
        case 'book':
          $props['rdf:type'][] = array('value'=>'bibo:Book', 'type'=>'uri');        
        break;
        
        case 'bookSection':
          $props['rdf:type'][] = array('value'=>'bibo:BookSection', 'type'=>'uri');        
        break;
        
        case 'case':
          $props['rdf:type'][] = array('value'=>'bibo:LegalDecision', 'type'=>'uri');        
        break;
        
        case 'computerProgram':
          $props['rdf:type'][] = array('value'=>'bibo:Document', 'type'=>'uri');
          $props['rdf:type'][] = array('value'=>'sc:ComputerProgram_CW', 'type'=>'uri');
        break;
        
        case 'conferencePaper':
          $props['rdf:type'][] = array('value'=>'bibo:Article', 'type'=>'uri');
        break;
        
        case 'dictionaryEntry':
          $props['rdf:type'][] = array('value'=>'bibo:Article', 'type'=>'uri');
        break;
        
        case 'document':
          $props['rdf:type'][] = array('value'=>'bibo:Document', 'type'=>'uri');
        break;
        
        case 'email':
          $props['rdf:type'][] = array('value'=>'bibo:Email', 'type'=>'uri');
        break;
        
        case 'encyclopediaArticle':
          $props['rdf:type'][] = array('value'=>'bibo:Article', 'type'=>'uri');
        break;
        
        case 'film':
          $props['rdf:type'][] = array('value'=>'bibo:Film', 'type'=>'uri');
        break;
        
        case 'forumPost':
          $props['rdf:type'][] = array('value'=>'bibo:Article', 'type'=>'uri');
          $props['rdf:type'][] = array('value'=>'sioct:BoardPost', 'type'=>'uri');
        break;
        
        case 'hearing':
          $props['rdf:type'][] = array('value'=>'bibo:Hearing', 'type'=>'uri');
        break;
        
        case 'instantMessage':
          $props['rdf:type'][] = array('value'=>'bibo:PersonalCommunication', 'type'=>'uri');
          $props['rdf:type'][] = array('value'=>'sioct:InstantMessage', 'type'=>'uri');
        break;
        
        case 'interview':
          $props['rdf:type'][] = array('value'=>'bibo:Interview', 'type'=>'uri');
        break;
        
        case 'journalArticle':
          $props['rdf:type'][] = array('value'=>'bibo:AcademicArticle', 'type'=>'uri');
        break;
        
        case 'letter':
          $props['rdf:type'][] = array('value'=>'bibo:Letter', 'type'=>'uri');
        break;
        
        case 'magazineArticle':
          $props['rdf:type'][] = array('value'=>'bibo:Article', 'type'=>'uri');
        break;
        
        case 'manuscript':
          $props['rdf:type'][] = array('value'=>'bibo:Manuscript', 'type'=>'uri');
        break;
          
        case 'map':
          $props['rdf:type'][] = array('value'=>'bibo:Map', 'type'=>'uri');
        break;
        
        case 'newspaperArticle':
          $props['rdf:type'][] = array('value'=>'bibo:Article', 'type'=>'uri');
        break;
        
        case 'note':
          $props['rdf:type'][] = array('value'=>'bibo:Note', 'type'=>'uri');
        break;
        
        case 'patent':
          $props['rdf:type'][] = array('value'=>'bibo:Patent', 'type'=>'uri');
        break;
        
        case 'podcast':
          $props['rdf:type'][] = array('value'=>'bibo:AudioDocument', 'type'=>'uri');
          $props['rdf:type'][] = array('value'=>'z:Podcast', 'type'=>'uri');
        break;
        
        case 'presentation':
          $props['rdf:type'][] = array('value'=>'bibo:Slideshow', 'type'=>'uri');
        break;
        
        case 'radioBroadcast':
          $props['rdf:type'][] = array('value'=>'po:Broadcast', 'type'=>'uri');
          $props['dcterms:medium'][] = array('value'=>'po:Radio', 'type'=>'uri');
        break;
        
        case 'report':
          $props['rdf:type'][] = array('value'=>'bibo:Report', 'type'=>'uri');
        break;
        
        case 'statute':
          $props['rdf:type'][] = array('value'=>'bibo:Statute', 'type'=>'uri');
        break;
        
        case 'thesis':
          $props['rdf:type'][] = array('value'=>'bibo:Thesis', 'type'=>'uri');
        break;
        
        case 'tvBroadcast':
          $props['rdf:type'][] = array('value'=>'po:Broadcast', 'type'=>'uri');
          $props['dcterms:medium'][] = array('value'=>'po:TV', 'type'=>'uri');
        break;
        
        case 'videoRecording':
          $props['rdf:type'][] = array('value'=>'bibo:AudioVisualDocument', 'type'=>'uri');
        break;
                
        case 'webpage':
          $props['rdf:type'][] = array('value'=>'bibo:Webpage', 'type'=>'uri');
        break;
        

      }    	
      
      return $props;  
    }
    
    private function filterFields($props) {
      
      foreach($this->fields as $field=>$value) {

        switch($field) {
                      
          case 'url':
            $props['bibo:uri'][] = array('value'=>$value, 'type'=>'uri');
          break;

          
          case 'rights':
            $props['dcterms:rights'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'volume':
          case 'codeVolume':
          case 'reporterVolume':
            $props['bibo:volume'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'issue':
            $props['bibo:issue'][] = array('value'=>$value, 'type'=>'literal');          
          break;
                      
          case 'edition':
          
          break;
          
          case 'place':          
            $props = $this->buildTripleChain($value,  'dcterms:publisher' , 'address:localityName', array('foaf:Organization'), $props );          
          break; 
          
          case 'country':
            $props = $this->buildTripleChain($value, 'dcterms:publisher', 'address:countryName', array('foaf:Organization'), $props);
          break;
          
          case 'publisher':
          case 'institution':
          case 'label':
          case 'studio':
          case 'network':
          case 'company':
          case 'university':
            $props = $this->buildTripleChain($value, 'dcterms:publisher', 'foaf:name', array('foaf:Organization'), $props);
          break;
                      
          case 'pages':
          case 'codePages':
            $props['bibo:pages'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'firstPage':
            $props['bibo:pageStart'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'ISBN':
            if(strlen($value) == 10 ) {
              $props['bibo:isbn10'][] = array('value'=>$value, 'type'=>'literal');	
            }
            if(strlen($value) == 13 ) {
            	$props['bibo:isbn13'][] = array('value'=>$value, 'type'=>'literal');
            }
          break;
          
          case 'publicationTitle':
          case 'encyclopediaTitle':
          case 'dictionaryTitle':
          case 'websiteTitle':
          case 'forumTitle':
          case 'blogTitle':
          case 'proceedingsTitle':
          case 'bookTitle':
            $props['dcterms:title'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'ISSN':
            $props['bibo:issn'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'date':
          case 'issueDate':
          case 'dateDecided':
          case 'dateEnacted':
            $props['dcterms:date'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'section':
            $props['bibo:section'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'callNumber':
            $props['bibo:lccn'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'archiveLocation':
            $props['dcterms:source'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'distributor':
            $props['bibo:distributor'][] = array('value'=>$value, 'type'=>'literal');
          break;                   
          
          case 'extra':
            $props['z:extra'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'journalAbbreviation':
            $props['bibo:shortTitle'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'DOI':
            $props['bibo:DOI'][] = array('value'=>$value);
          break;
          
          case 'accessDate':
            $props['z:accessDate'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'seriesTitle':
            $props['dcterms:title'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'seriesText':
            $props['dcterms:description'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'seriesNumber':
            $props['bibo:number'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'code':
            $props['dcterms:title'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'session':
            $props['dcterms:title'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'legislativeBody':
            $props = $this->buildTripleChain($value, 'foaf:name', 'bibo:organizer', array('sc:LegalGovernmentOrganization', 'foaf:Organization'), $props);
          break;
          
          case 'history':
            $props['z:history'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'reporter':
            $props['dcterms:title'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'court':
            $props['bibo:court'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'numberOfVolumes':
            //bibo prop only proposed https://www.zotero.org/trac/wiki/BiboMapping
          break;
          
          case 'committee':
            $props = $this->buildTripleChain($value, 'foaf:name', 'bibo:organizaer', array('sc:Committee_Organization', 'foaf:Organization'), $props);  
          break;
          
          case 'assignee':
            //
          break;
          
          case 'priorityNumbers':
            //
          break;
          
          case 'references':
            $props['z:references'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'legalStatus':
            $props['bibo:status'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'patentNumber': 
          case 'reportNumber': 
          case 'billNumber': 
          case 'documentNumber': 
          case 'publicLawNumber': 
          case 'episodeNumber': 
          case 'docketNumber': 
          case 'applicationNumber': 
            $props['bibo:number'][] = array('value'=>$value);
          break;
          
          case 'artworkSize':
            $props['dcterms:extent'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'repository':
            $props['z:repository'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'scale':
          //
          break;
          
          case 'meetingName':
            $props['dcterms:title'][] = array('value'=>$value, 'type'=>'literal');
          break;          
          
          case 'runningTime':
            $props['po:duration'][] = array('value'=>$value);
          break;
          
          case 'version':
            if($this->itemType == 'computerProgram') {
              $props['doap:revision'][] = array('value'=>$value, 'type'=>'literal');
            }
          break;
          
          case 'system':
            $props['doap:os'][] = array('value'=>$value);
          break;
          
          case 'conferenceName':
            $props['dcterms:title'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'language':
            $props['dcterms:language'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'programmingLanguage':
            $props['doap:programming-language'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'abstractNote':
            $props['dcterms:abstract'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'type': 
          case 'reportType':
          case 'videoRecordingType':
          case 'letterType':
          case 'manuscriptType':
          case 'mapType':
          case 'thesisType':
          case 'websiteType':
          case 'audioRecordingType':
          case 'presentationType':
          case 'postType':
          case 'audioFileType':
            $props['dcterms:type'][] = array('value'=>$value, 'type'=>'literal');
          break; 
           
          case 'medium':
          case 'artworkMedium':
          case 'interviewMedium':
            $props['dcterms:medium'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
            
          case 'title':
          case 'caseName':
          case 'nameOfAct':
          case 'subject':
            $props['dcterms:title'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'shortTitle':
            $props['bibo:shortTitle'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'numPages':
            //
          break;
      /* Creator properties */
      
          case 'artist':
            $props['marcrel:ART'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'attorneyAgent':
          //
          break;
          
          case 'author':
            $props['dcterms:creator'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'cartographer':
            $props['marcrel:CTG'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'castMember':
            $props['bibo:performer'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'commenter':
            if($this->itemType == 'blogPost') {
            	$props = $this->buildTripleChain($value, 'dcterms:creator', 'sioct:has_reply', array('sioct:Comment'), $props);
            }
          break;
          
          case 'composer':
            $props['marcrel:CMP'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'contributor':
            $props['dcterms:contributor'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'counsel':
            //
          break;
          
          case 'director':
            $props['bibo:director'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'editor':
            $props['bibo:editor'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'guest':
            $props['marcrel:CMM'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'interviewer':
            $props['bibo:interviewer'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'interviewee':
            $props['bibo:interviewee'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'inventor':
            $props['marcrel:INV'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'performer':
            $props['bibo:performer'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'podcaster':
            $props['marcrel:SPK'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'presenter':
            $props['marcrel:SPK'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'producer':
            $props['bibo:producer'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'programmer':
            $props['marcrel:PRG'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'recipient':
            $props['bibo:recipient'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'reviewedAuthor':
            $props = $this->buildTripleChain($value, 'dcterms:creator', 'bibo:reviewOf', array('foaf:Document'), $props);
          break;
          
          case 'scriptwriter':
            $props['marcrel:AUS'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'seriesEditor':
            $props['bibo:editor'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'sponsor':
            $props['marcrel:FND'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'translator':
            $props['bibo:translator'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
          case 'wordsBy':
            $props['marcrel:LYR'][] = array('value'=>$value, 'type'=>'literal');
          break;
          
        }
        
      
    }
    return $props;
  }
}

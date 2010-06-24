<?php

define('ZITEM_ARC_PATH', '../../arc/ARC2.php');

class ZItem {

    public $doc;
    public $xpath;
    public $fields;
    public $itemType;
    public $dateAdded;
    public $dateModified;
    
    
    public function ZItem($item) {
    	$this->doc = new DOMDocument();
      if (is_string($item)) {
      	$this->doc->loadXML($item);
      } else if(get_class($item) == 'DOMElement') {        
      	$newItemNode = $this->doc->importNode($item, true);
        $this->doc->appendChild($newItemNode);
      } else {
      	throw new Exception('Item must be either an XML string or a DOMNode');        
      }
      
      $this->xpath = new DOMXPath($this->doc);
      $this->xpath->registerNamespace('zxfer', 'http://zotero.org/ns/transfer');
      
      $this->itemType = $this->xpath->query('//zxfer:item/@itemType')->item(0)->textContent;
      $this->dateAdded = $this->xpath->query('//zxfer:item/@dateAdded')->item(0)->textContent;
      $this->dateModified = $this->xpath->query('//zxfer:item/@dateModified')->item(0)->textContent;
      $fields = $this->xpath->query('//zxfer:field');
      for($i=0; $i < $fields->length; $i++) {
        $fieldName = $fields->item($i)->getAttribute('name');
        $this->fields[$fieldName] = $fields->item($i)->textContent;
      }          
    }

    
    public function getFields() {
    	return $this->fields;
    }
    
    public function getFieldsAsJSON() {
    	return json_encode($this->fields, true);
    }
    
    public function getItemAsJSON() {
    	$itemObj = new StdClass();
      $itemObj->itemType = $this->itemType;
      $itemObj->dateAdded = $this->dateAdded;
      $itemObj->dateModified = $this->dateModified;
      $itemObj->fields = $this->fields;
      return json_encode($itemObj, true);
    }
    
    public function fieldExists($field) {
    	return array_key_exists($field, $this->fields);
    }
    
    public function getItemAsRdf($itemUri, $format = 'rdf/xml') {
    	require_once(ZITEM_ARC_PATH);
      $conf = array(
        'ns' => array(
          'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
          'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
          'bibo' => 'http://http://purl.org/ontology/bibo/',
          'dcterms' => 'http://purl.org/dc/terms/',
          'po' => 'http://purl.org/ontology/po/',
          'doap' => 'http://usefulinc.com/ns/doap#',
          'sioct' => 'http://rdfs.org/sioc/types#',
          'sc' => 'http://umbel.org/umbel/sc/',
          'z' => 'http://www.zotero.org/namespaces/export#'
        )
      );
      $res = ARC2::getResource($conf);
      $res->setURI($itemUri);
      
      
      //$props = array();
      switch($this->itemType) {
        case 'artwork':
          $props['rdf:type'] = array('bibo:Image');        
        break;
        
        case 'attachment':
          $props['rdf:type'] = array('z:Attachment');
        break;
                
        case 'audioRecording':
          $props['rdf:type'] = array('bibo:AudioDocument');        
        break;
        
        case 'bill':
          $props['rdf:type'] = array('bibo:Bill');        
        break;
        
        case 'blogPost':
          $props['rdf:type'] = array('bibo:Article', 'sioct:BlogPost'); 
        break;
        
        case 'book':
          $props['rdf:type'] = array('bibo:Book');        
        break;
        
        case 'bookSection':
          $props['rdf:type'] = array('bibo:BookSection');        
        break;
        
        case 'case':
          $props['rdf:type'] = array('bibo:LegalDecision');        
        break;
        
        case 'computerProgram':
          $props['rdf:type'] = array('bibo:Document', 'sc:ComputerProgram_CW');        
        break;
        
        case 'conferencePaper':
          $props['rdf:type'] = array('bibo:Article');
        break;
        
        case 'dictionaryEntry':
          $props['rdf:type'] = array('bibo:Article');
        break;
        
        case 'document':
          $props['rdf:type'] = array('bibo:Document');
        break;
        
        case 'email':
          $props['rdf:type'] = array('bibo:Email');
        break;
        
        case 'encyclopediaArticle':
          $props['rdf:type'] = array('bibo:Article');
        break;
        
        case 'film':
          $props['rdf:type'] = array('bibo:Film');
        break;
        
        case 'forumPost':
          $props['rdf:type'] = array('bibo:Article', 'sioct:BoardPost');
        break;
        
        case 'hearing':
          $props['rdf:type'] = array('bibo:Hearing');
        break;
        
        case 'instantMessage':
          $props['rdf:type'] = array('bibo:PersonalCommunication', 'sioct:InstantMessage');
        break;
        
        case 'interview':
          $props['rdf:type'] = array('bibo:Interview');
        break;
        
        case 'journalArticle':
          $props['rdf:type'] = array('bibo:AcademicArticle');
        break;
        
        case 'letter':
          $props['rdf:type'] = array('bibo:Letter');
        break;
        
        case 'magazineArticle':
          $props['rdf:type'] = array('bibo:Article');
        break;
        
        case 'manuscript':
          $props['rdf:type'] = array('bibo:Manuscript');
        break;
          
        case 'map':
          $props['rdf:type'] = array('bibo:Map');
        break;
        
        case 'newspaperArticle':
          $props['rdf:type'] = array('bibo:Article');
        break;
        
        case 'note':
          $props['rdf:type'] = array('bibo:Note');
        break;
        
        case 'patent':
          $props['rdf:type'] = array('bibo:Patent');
        break;
        
        case 'podcast':
          $props['rdf:type'] = array('bibo:AudioDocument', 'z:Podcast');
        break;
        
        case 'presentation':
          $props['rdf:type'] = array('bibo:Slideshow');
        break;
        
        case 'radioBroadcast':
          $props['rdf:type'] = array('po:Broadcast');
          $props['dcterms:medium'] = array('po:Radio');
        break;
        
        case 'report':
          $props['rdf:type'] = array('bibo:Report');
        break;
        
        case 'statute':
          $props['rdf:type'] = array('bibo:Statute');
        break;
        
        case 'thesis':
          $props['rdf:type'] = array('bibo:Thesis');
        break;
        
        case 'tvBroadcast':
          $props['rdf:type'] = array('po:Broadcast');
          $props['dcterms:medium'] = array('po:TV');
        break;
        
        case 'videoRecording':
          $props['rdf:type'] = array('bibo:AudioVisualDocument');
        break;
                
      	case 'webpage':
          $props['rdf:type'] = array('bibo:Webpage');
        break;
        

      }
      
      foreach($this->fields as $field=>$value) {
      	switch($field) {
          case 'rights':
            $props['dcterms:rights'] = array($value);
          break;
          
          case 'pages':
          case 'codePages':
            $props['bibo:pages'] = array($value);
          break;
          
          case 'firstPage':
            $props['bibo:pageStart'] = array($value);
          break;
          
          case 'section':
            $props['bibo:section'] = array($value);
          break;
          
          case 'archiveLocation':
            $props['dcterms:source'] = array($value);
          break;
          
          case 'extra':
            $props['z:extra'] = array($value);
          break;
          
          case 'DOI':
            $props['bibo:DOI'] = array($value);
          break;
          
          case 'committee':
            //$props['bibo:uri'] = array($value);
          break;
          
          case 'assignee':
            //$props['bibo:uri'] = array($value);
          break;
          
          case 'priorityNumbers':
            //$props['bibo:uri'] = array($value);
          break;
          
          case 'references':
            //$props['z:references'] = array($value);
          break;
          
          case 'legalStatus':
            $props['bibo:status'] = array($value);
          break;
          
          case 'patentNumber': 
          case 'reportNumber': 
          case 'billNumber': 
          case 'documentNumber': 
          case 'publicLawNumber': 
          case 'episodeNumber': 
          case 'docketNumber': 
          case 'applicationNumber': 
            $props['bibo:number'] = array($value);
          break;
          
          case 'scale':
            //$props['bibo:uri'] = array($value);
          break;
          
          case 'runningTime':
            $props['po:duration'] = array($value);
          break;
          
          case 'version':
            //$props['bibo:uri'] = array($value);
          break;
          
          case 'system':
            $props['doap:os'] = array($value);
          break;
          
          case 'language':
            $props['dcterms:language'] = array($value);
          break;
          
          case 'programmingLanguage':
            $props['doap:programming-language'] = array($value);
          break;
          
          case 'abstractNote':
            $props['dcterms:abstract'] = array($value);
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
            $props['dcterms:type'] = array($value);
          break; 
           
          case 'medium':
          case 'artworkMedium':
          case 'interviewMedium':
            $props['dcterms:medium'] = array($value);
          break;
          
            
      		case 'title':
          case 'caseName':
          case 'nameOfAct':
          case 'subject':
            $props['dcterms:title'] = array($value);
          break;
          
          case 'shortTitle':
            $props['bibo:uri'] = array($value);
          break;
          
          case 'numPages':
            //$props['bibo:uri'] = array($value);
          break;
          
          case 'url':
            $props['bibo:uri'] = array($value);
          break;
          
          

      	}
        
        $res->setProps($props);
        
        switch($format) {
          case 'rdf/xml':
            $ser = ARC2::getRDFXMLSerializer($conf);        	  
          break;
          
          case 'rdf/json':
            $ser = ARC2::getRDFJSONSerializer($conf);            
          break;
          
          case 'turtle':
            $ser = ARC2::getTurtleSerializer($conf);                       
          break;
          
          case 'ntriples':
            $ser = ARC2::getNTriplesSerializer($conf);            
          break;
          
        }        
        return $ser->getSerializedIndex($res->index);
      }
    }    
}

?>

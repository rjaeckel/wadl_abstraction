<?php

/*
namespace mlu\common\functions;
use XMLReader;
/*
include 'xml2assoc.fn.php';
/*
define('export_namespaces',[
	'ns2'=>'wadl\\groupwise',
  'ns1'=>'wadl\\atom'
]);
*/
namespace wadl;
use XMLReader;
use XMLReader as XML;

error_reporting(E_ALL|E_STRICT);


Class XMLNode {
	/**
	 * XMLReader node types to be inspected during read
	 */
	const childTypes=[
		XML::ELEMENT,
		XML::CDATA,
		XML::TEXT ];

	protected static $curType =false;

	function __construct (\XMLReader $xml) {
		$this->xml=$xml;
		$this->read();
	}

	protected function readXML() {
		return static::$curType=(static::$curType||$this->xml->read())?$this->xml->nodeType:false;
	}

	protected function readTag() {
		$this->tagName   =$this->xml->localName;
		$this->namespace =$this->xml->prefix;
		$this->xmlDepth  =$this->xml->depth;
		static::$curType=null;
	}

	protected function readChildren(){
		if(!$this->xml->isEmptyElement) {
			while ($type=$this->readXML()) {
				if ( $type == XML::END_ELEMENT ) break;
				if(in_array($type,static::childTypes)) {
					$child=new static( $this->xml );
					($t=$child->__textValue??false)?($this->text=$t):($this->children[]=$child);
					#$child=$child->read ();
					#$child instanceof static &&($this->children[]=$child)
					#||$this->text=$child;
					#$this->children[]=$child;
				}
			}
		}
	}
	protected function readAttributes() {
		if ($this->xml->hasAttributes)
			while ( $this->xml->moveToNextAttribute () )
				$this->{$name = $this->xml->name} = $value=$this->xml->value;
		static::$curType =false;
	}
	protected function readText() {
		static::$curType =false;
		return
			$this->__textValue=trim($this->xml->value);
	}

	function read() {
		# read element or hit end of file
		if (!$this->readXML()) return;
		switch ($this->xml->nodeType) {
			case XML::CDATA:
			case XML::TEXT:
			#return
			  $this->readText();
				break;
			case XML::ELEMENT:
				# watch order!
				$this->readTag();
				$this->readChildren();
				$this->readAttributes();
				break;
		}
		unset($this->xml);
		return $this;
	}

}

function __main() {

	$wadl_file = 'test.wadl';
	$reader    = new \XMLReader();
	$reader->open ( $wadl_file );

	$test = new XMLNode($reader);
	#$test2=$test->read( true);
	#echo json_encode($test,JSON_PRETTY_PRINT,10000);
	#assert($test==$test2,"retrun equals object called")
	#&&
	fwrite(STDERR,print_r($test,true))
	#||fwrite(STDERR,print_r($test2,true))
	;
}



if(__FILE__===realpath($argv[0]))__main(...$argv);



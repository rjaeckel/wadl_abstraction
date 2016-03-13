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
use XMLReader as XML;
use callByRegex,collectionIterator;

error_reporting(E_ALL|E_STRICT);

require_once ('includes/callByRegex.php');
require_once ('includes/collectionIterator.php');


/**
 * Class XMLNode
 *
 * @package wadl
 *
 * @method $this eachChild(callable $cb,...$args)
 * @method $this eachOf(string $type,callable $cb, ...$args)
 * @method $this|array eachChildHaving(callable ...$cb)
 */
class XMLNode {
	use collectionIterator ,callByRegex {
		callByRegex::handleRegex as __call;
	}
	/**
	 * XMLReader node types to be inspected during read
	 */
	const childTypes=[
		XML::ELEMENT,
		XML::CDATA,
		XML::TEXT ];

	protected static $curType =false;
	protected        $xml;
	public           $tagName, $tagPrefix;
	/**
	 * @var self[]
	 */
	public           $children=[];
	#public $attributes=[];

	function __construct (XML $xml) {
		$this->xml=$xml;
		$this->read();
		$this
			->addHandler('eachChild','each_children')
			#->addHandler('eachAttr','each_attributes')
			->addHandler('eachOf(.+)','eachChildOf')
			->addHandler('each_(.+)_having','each_having')
			->addHandler('eachChildHaving','each_children_having')
			->addHandler('each_(.+)','each');
	}

	function __debugInfo () {
		$res=[];
		foreach ( $this as $k=>$v ) {
			empty($v)||$k=='handlers'||($res[$k]=$v);
		}
		return $res;
	}

	function eachChildOf(string $elemName,$cb,...$args){
		return $this->each_having('children',['tagName'=>$elemName],$cb,...$args);
	}

	protected function readXML() {
		static::$curType=(static::$curType||$this->xml->read())?$this->xml->nodeType:false;
		return static::$curType;
	}

	protected function readTag() {
		$this->tagName   = $this->xml->localName;
		$this->tagPrefix = $this->xml->prefix;
		#$this->depth     = $this->xml->depth;
		static::$curType=null;
	}

	protected function readChildren(){
		if(!$this->xml->isEmptyElement) {
			while ($type=$this->readXML()) {
				if ( $type == XML::END_ELEMENT ) break;
				if(in_array($type,static::childTypes)) {
					$child=$this->createChild();
					$this->appendChild($child);
				}
				static::$curType=null;
			}
		}
	}

	protected function appendChild(XMLNode $child) {
		($t=$child->__textValue??false)
			?($this->text=$t)
			:($this->children[]=$child)
		;
	}

	/**
	 * @return self
	 */
	protected function createChild() {
		return new static($this->xml);
	}

	protected function readAttributes() {
		if ($this->xml->hasAttributes)
			while ( $this->xml->moveToNextAttribute () )
				#$this->attributes[$this->xml->name] = $this->xml->value;
				$this->{$this->xml->name} = $this->xml->value;
		static::$curType =false;
	}

	protected function readText() {
		static::$curType =false;
		return
			$this->__textValue=trim($this->xml->value);
	}

	protected function read() {
		# read element or hit end of file
		if (!$this->readXML()) return;
		switch ($this->xml->nodeType) {
			case XML::CDATA:
			case XML::TEXT:
			  $this->readText();
				break;
			case XML::ELEMENT:
				# watch order!
				$this->readTag();
				$this->readChildren();
				$this->readAttributes();
				break;
		}
		static::$curType=null;
		unset($this->xml);
		return $this;
	}
	public function __toString () {
		return '';
	}

	static function __main($name,$file='wadl_schemata/test.wadl') {

		$reader = new \XMLReader();
		$reader->open ( $file );

		$test = new static ( $reader );
		#fwrite(STDERR,print_r($test,true));
		$test->eachChild ( $fn = function ( XMLNode $node ) use ( &$fn ) {
			static $depth = 0;
			echo str_repeat ( "\t" , $depth ) . " $node->tagName\n";
			$depth++;
			#$node->eachChild($fn);
			$depth--;
		});
		echo $test;
	}

}


if(__FILE__===realpath($argv[0])) XMLNode::__main(...$argv);



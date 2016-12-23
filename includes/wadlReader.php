#!/usr/bin/env php
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
use callByRegex;
error_reporting(E_ALL|E_STRICT);

require_once 'includes/callByRegex.php';

Class xxxXMLNode {
	function __construct (\XMLReader $xml) {
		$this->xml=$xml;
	}
	function __toString () {
		return json_encode($this);
	}
	function __debugInfo () {
		$res=(array)$this;unset($res['xml']);
		return $res;
	}

	function read($isRoot=false){
		static $inElement=0;
		// $tree=null #some kind of container
		$tree=($isRoot||$inElement)?$this:new self($this->xml);
		assert('$tree===$this',static::class."::read($isRoot) \$tree===\$this");
		#echo "pre-Read-Depth: ".$this->xml->depth;

		while( !($inElement||$isRoot) || $this->xml->read() ) {
			switch ($this->xml->nodeType) {
				case XMLReader::END_ELEMENT:
					$inElement--;
					return $tree; #$node?
				case XMLReader::ELEMENT:
					$node = $isRoot? $this: new static($this->xml);
					@assert($node===$this,static::class."::read($isRoot) \$node===\$this")
					||print('.');
					//$node->nameNS=$this->xml->name;
					$node->tagName=$this->xml->localName;
					$node->namespace=$this->xml->prefix;
					$node->XMLdepth=$this->xml->depth;
					if(!$this->xml->isEmptyElement) {
						// has children?
						$inElement++;
						$value=$node->read();
						assert('$value===$node');
						if(isset($this->children)) {
							assert('count($this->children)');
							assert('$value!==$this->children[0]');
							assert('$value!==($lastChild=array_pop($this->children))&&($this->children[]=$lastChild)');
						}

						#$inElement=false;
					}
					// read attrs
					if($this->xml->hasAttributes) {
						while($this->xml->moveToNextAttribute()) {
							$node->{$this->xml->name} = $this->xml->value;
						}
					}
					assert($node!==$this,"Will not append self to children (d: ".$this->xml->depth.")");
					$node!==$this&&($tree->children[]=$node);

					break;
				case XMLReader::TEXT:
				case XMLReader::CDATA:
					@$tree->text .= $this->xml->value;
				break;
			}
		}
		//return $tree;
	}
}

class Reader{
	use callByRegex{
		callByRegex::handleRegex as __call;
	}
	static $recursion=0;
	/**
	 * @var \XMLReader
	 */
	protected static $xml;
	/**
	 * @var string[]
	 */
	public $Attrs;
	/**
	 * @var self[]
	 */
	public $Child;
	/**
	 * @var string
	 */
	public $name;
	/**
	 * @var string
	 */
	public $nameNS;
	/**
	 * @var string
	 */
	protected $text;

	function __construct (\XMLReader $pointer=null,$singleRootNode=false) {
		self::$recursion++;
		$this->addHandler("/each(.*)/",[$this,'each']);
		$this->depth=self::$recursion;
		self::$xml=$pointer??self::$xml;
		$this
			->readElement($singleRootNode)
			->readAttributes()
			->readChildren();
		$this->printE(str_repeat('  ',self::$recursion)."DONE $this->nameNS\n");
		self::$recursion--;
	}
	function printType(int $type) {
		$ref=new \ReflectionClass('XMLReader');
		return array_search($type,$ref->getConstants())??"UNDEF($type)";
	}
	protected function readChildren(){
		static $counter=0;
		$this->printE('Children? ');
		if(self::$xml->isEmptyElement) {
			$this->printE("EMPTY\n");
			return;
		}
		$this->printE("YES\n");
		while (self::$xml->read()) {
			($this->debug??false) &&
			$this->printE(str_repeat('  ',self::$recursion)."READ",++$counter,$this->printType(self::$xml->nodeType),"\n");

			switch(self::$xml->nodeType) {
				// break the whole read on end of element
				case \XMLReader::END_ELEMENT:
					$this->printE( str_repeat('  ',self::$recursion)."EOE found $this->nameNS/",self::$xml->name,"\n");
					#if(in_array($lastType,[\XMLReader::CDATA,\XMLReader::TEXT])) yield $this;
					return;
				case \XMLReader::ELEMENT:
					$this->Child[]=new self();
					break;
				case \XMLReader::CDATA:
				case \XMLReader::TEXT:
					#$this->readText();
					break;
			}
		}
		return ;
	}
	protected function close(){
		static $closes=0;
		isset(self::$xml)&&self::$xml->close();
		$this->printE('CLOSE',++$closes);
		self::$xml=null;
	}

	protected function readElement( $performRead=false){
		$p=self::$xml;
		$performRead && $p->read();
		$this->nameNS=$p->name;
		$this->name=$p->localName;
		$this->printE(str_repeat('  ',self::$recursion)."found ELEM <$this->nameNS> ");
		return $this;
	}
	protected function readAttributes() {
		$p=self::$xml;
		if($p->hasAttributes) {
			//$this->printE(" ATTRIBUTES: ");
			while($p->moveToNextAttribute()) {
				$this->Attrs[ $name=$p->name] = $value=$p->value;
				//$this->printE("$name=$value ");
			}
			//$this->printE("\n");
		}
		return $this;
	}
	protected function readText() {
		if (in_array(self::$xml->nodeType,[\XMLReader::CDATA,\XMLReader::TEXT])) {
			$this->printE ( str_repeat('  ',self::$recursion)."TEXT node\n" );
			$this->text .= self::$xml->value;
		}
		return $this;
	}
	function debug($recurse=0,$attrs=['nameNS','Attrs','text','Child']) {
		$res=[];
		foreach ( $attrs as $item ) {
			if($item=='Child') continue;
			#echo "$item \n";
			$res[$item]=$this->$item;
		}
		print(in_array('Child',$attrs))&&
		$res[@Child]=$recurse?array_map(function(Reader $item)use($recurse,$attrs){
			return $item->debug($recurse-1,$attrs);
		}, $this->Child??[]):count( $this->Child);
		return $res;
	}
	public function printE(...$string) {
		if (isset($this->debug))
			return fwrite(STDERR,implode(' ',$string));
	}
	protected $debug=true;
	/**
	 * @param string   $property
	 * @param callable $cb
	 * @param array    ...$args
	 * @return $this
	 */
	function each(string $property,callable $cb,...$args) {
		foreach($this->$property??[] as $k=>&$item){
			$this->$property[$k]=$cb($item,$k,...$args)??$item;
		}
		return $this;
	}
}

function __main() {

	#assert_options(ASSERT_ACTIVE, 1);
	#assert_options(ASSERT_WARNING, 0);
	ini_set('zend.assertions',true);

	//ini_set(assert.exception,true)
	//assert_options(ASSERT_QUIET_EVAL, 1);
	$wadl_file = 'wadl_schemata/test.wadl';
	$reader    = new \XMLReader();
	$reader->open ( $wadl_file );

	//$test = new Reader( $reader ,true );
	$test = new xxxXMLNode($reader);
	$test2=$test->read(true);
	assert($test==$test2,"retrun equals object called");
	fwrite(STDERR,print_r($test,true));

}

(__FILE__==realpath($argv[0]))&&__main(...$argv);



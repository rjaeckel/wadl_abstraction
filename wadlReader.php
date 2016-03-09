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

trait callByRegex {
	/**
	 * @var callable[]
	 */
	private static $handlers =[];

	/**
	 * @param string   $regex
	 * @param callable $call
	 */
	protected function addHandler (string $regex,callable $call) {
		static::$handlers[ $regex ] =$call;
	}

	/**
	 * @param string $name
	 * @param array  $args
	 */
	protected function handleRegex(string $name,array $args) {
		foreach(static::$handlers as $regEx=>$cb) {
			if(
				preg_match($regEx,$name,$matches)&&
				$name==array_shift($matches)&&
				($res=$cb(...$matches,...$args))
			) return $res;
		}
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

	$wadl_file = 'application.wadl';
	$reader    = new \XMLReader();
	$reader->open ( $wadl_file );

	$test = new Reader( $reader ,true );

	echo json_encode($test->debug(5,['nameNS','depth','Child','text','Attrs']),JSON_PRETTY_PRINT);
	/*print_r($test->eachChild($f=function(Reader $child,$idx,string $parent)use(&$f){
		return "$parent.$idx # $child->nameNS [\n".implode($child->eachChild($f,"$parent.$idx."))." ],\n";
	},"## ")->debug(4,['nameNS','Child']));*/
	#print_r ( $test->debug ( 2 ) );

	}


if(__FILE__===realpath($argv[0]))__main(...$argv);



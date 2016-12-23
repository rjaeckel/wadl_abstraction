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
namespace xs;
use XMLReader as XML;
use callByRegex,collection,wadl\XMLTree;

error_reporting(E_ALL|E_STRICT);

require_once ('includes/t-callByRegex.php');
require_once ('includes/t-collection.php');
require_once ('wadlReader2.php');
/**
 * Class schema
 *
 * @package wadl
 *
 * @method $this eachChild(callable $cb,...$args)
 * @method $this|array eachChildHaving(callable ...$cb)
 */


class schema extends XMLTree
{
	protected static $rename = [ 'list' => 'list_' ];
	protected static $ifNamespace;

	protected $isRoot;

	protected static $typesRead       = [ ];
	protected static $typesReferenced = [ ];

	/**
	 * @return self
	 */
	protected function createChild () {
		$absClass = $this->ifClass ( $this->xml->name );
		if ( ! class_exists ( $absClass , false ) ) {
			in_array ( $absClass , [ '\\#text' ] ) || trigger_error ( "Class $absClass not existent" , E_USER_WARNING );
			$absClass = XMLTree::class;
		}
		return new $absClass ( $this->xml );
	}

	public function __debugInfo () {
		$res=parent::__debugInfo ();
		unset($res['children']);
		ksort($res);
		return $res;
	}

	protected function appendChild (XMLTree $child ) {
		if(0===strpos($child->tagName,'any')) return;
		if ( $this->isRoot ) {
			# named element: attribute, typedef
			if($name=$child->name??false) {static::$typesRead[]=$name;}
		}
		switch($child->tagName) {
			case 'union':
			case 'restriction':
			case 'complexType':
			case 'choice':
			case 'sequence':
			case 'complexContent':
				$key=$child->tagName;
				assert('!isset($this->$key)');
				$this->{$key}=$child;
			break;
			/*case 'element':
				$this->Element[]=$child;
			break;*/
			default:
				$this->{ucfirst($child->tagName)}[]=$child;
			break;
		}
		# simpleElement
		if($base=$child->base??$child->ref??$child->itemType??false) {
			static::$typesReferenced[]=$base;
		}
		#parent::appendChild ( $child );
	}

	protected function ifClass ( string $name ) {
		$namespace   = explode ( ':' , $name );
		$class       = array_pop ( $namespace );
		$targetClass = static::$rename[ $class ]??$class;
		return static::$ifNamespace . implode ( '\\' , array_merge ( $namespace , [ $targetClass ] ) );
	}

	public function __construct ( XML $xml , $interfaceNamespace = '\\' ) {

		static::$ifNamespace = $interfaceNamespace;
		parent::__construct ( $xml );
		#echo $this;
		#$this->init();
	}

	protected function readTag () {
		$this->isRoot = 0 == $this->xml->depth;
		parent::readTag ();
	}

	public function __toString () {
		$namespace = $this->ifClass ( $this->tagPrefix );

		\print_r($this);
		die();

		$head=$body=$foot='';
		if ( $this->isRoot ) {
			$head = "<?php namespace $namespace;\n";
			$body =implode($this->children);
		} elseif($name=$this->name??false) {
			$head = "class $name { \n";
			$foot="}\n";
		} else {

			#$head = "// HEAD $this->tagName\n";
			#$foot = "// FOOT $this->tagName\n";
		}
		if($complex=$this->complexType??false) {
			$head=$foot='';
			$body=$complex;
		}
		if(empty($body)) {
			foreach($this->children as $child) {
				$body.="// child: $child\n";
			}
		}

		return $head.$body.$foot;
	}
}

//require_once ( 'includes/xs_elements.php' );

if(__FILE__===realpath($argv[0])) schema::__main(...$argv);

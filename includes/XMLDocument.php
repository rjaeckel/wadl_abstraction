<?php

namespace r0b\xml;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;

ini_set('assert.exception',true);

class invocationHelper {
    protected $handle;
    public
    function __construct($obj) {
        $this->handle=$obj;
    }
    public
    function __call($name, $arguments) {
        return call_user_func([$this->handle,$name],...$arguments);
    }
}

/**
 * Class XMLDocument
 *
 * this class handles with pure xml data
 *
 * reason to adapt the api here is the fact that
 * instances of this class are available over the whole dom tree by accessing
 * DOMNode's property $ownerDocument @see DOMNode
 *
 * @property XMLElement $firstChild
 *
 * @package r0b\xml
 */
class XMLDocument extends DOMDocument
{
    const ns='xs';
    const namespaces = [self::ns => ['http://www.w3.org/2001/XMLSchema', 'xml_schemata/XMLSchema.xsd']];

    const xmlOptions = LIBXML_COMPACT | LIBXML_NOCDATA | LIBXML_NOBLANKS;

    const qUp='ancestor-or-self::';
    const qDown='descendant-or-self::';

    /** @var DOMXPath */
    protected $xpath;
    protected $namespaces=[];


    /**
     * Creates a new XML Document
     *
     * uses @see DOMDocument::registerNodeClass()
     * to inject new abilities to @see DOMElement
     * by replacing it with @see XMLElement
     *
     * @param string|null $xml_version
     * @param string|null $encoding
     *
     */
    public
    function __construct($xml_version = '1.0', $encoding = 'utf-8') {
        parent::__construct($xml_version, $encoding);

        $this->preserveWhiteSpace = FALSE;
        $this->namespaces=static::namespaces;

        $this->registerNodeClass(DOMElement::class, XMLElement::class);
    }

    public static
    function fromString($source, $options = NULL) {
        $res = new static();
        return $res->loadXML($source, $options);
    }

    /**
     * @param      $filename
     * @param null $options
     *
     * @return bool|static::class
     */
    public static
    function fromFile($filename, $options = NULL) {
        $res = new static();

        return $res->load((string)$filename, $options) ? $res : FALSE;
    }

    /**
     * @param $xpath
     * @param DOMNode|NULL $context
     * @return DOMNodeList|XMLElement[]
     */
    public
    function query($xpath, DOMNode $context = NULL) {
        return $this->xpath('query',''.$xpath, $context);
    }
    public
    function queryAll($xpath, DOMNode $context = NULL) {

        return $this->xpath('query','.//' . $xpath, $context);
    }
    public
    function queryUp($xpath, DOMNode $context = NULL) {
        return $this->xpath('query','ancestor-or-self::' . $xpath, $context);
    }
    public
    function queryDown($xpath, DOMNode $context = NULL) {
        return $this->xpath('query','descendant-or-self::' . $xpath, $context);
    }


    public
    function queryValue($xpath,DOMNode$context=null) {
        return $this->xpath->evaluate(''.$xpath,$context);
    }

    /**
     * @param string[] ...$funcNames
     */
    public
    function addPHPSelectors(...$funcNames) {
        $this("importNS php http://php.net/xpath");
        $this->xpath('registerPhpFunctions',empty($funcNames) ? NULL : $funcNames);
    }
    public
    function importNS($prefix,$uri,string $xsdFile=null) {
        return $this->xpath('registerNamespace', $prefix, $uri)
               && $this->namespaces[$prefix]=[$uri,$xsdFile];
    }
    public
    function __invoke($param,...$args) {
        $strAgs=preg_split('%\s+%',(string)$param);

        $method=array_shift($strAgs);
        #print_r([$method,$strAgs,$args]);

        $res=(new invocationHelper($this))->$method(...$strAgs,...$args);
        return $res;
    }

    /**
     * invoke a method on @see $xpath
     *
     * initiates $this->xpath
     * by calling
     *
     * @see loadXpath()
     *
     * @param string $methodName
     * @param mixed[] $args
     *
     * @return mixed
     */
    public
    function xpath($methodName,...$args){
        isset($this->xpath)||$this->loadXpath();

        return call_user_func([$this->xpath,$methodName],...$args);
    }
    protected function loadXpath() {
        $this->normalizeDocument();
        $this->xpath = new DOMXPath ($this);
        $regSucc=true;
        foreach ($this->namespaces as $key => list($uri, $schema)) {
            $registered=$this->xpath->registerNamespace( $key, $uri);
            $regSucc=$registered?$regSucc:FALSE;
        }
        return $regSucc;

    }

    public
    function each($qry,$method,...$args) {
        return $this->firstChild->each($qry,$method,...$args);
    }

    public
    function renderTree(DOMNode$context=null) {
        $nodes=[];
        foreach ($this->query('.//*',$context) as $c) {
            @$nodes[$c->nodeName]++;
            #echo $c->getNodePath()."\n";
            echo str_repeat('   ',$c->getNodeDepth()).$c."\n";
        }
        #print_r($nodes);
    }
    /*
    function q($elemName,$filter='',$context=null) {
        return$this->query(constant("static::$elemName").$filter,$context);
    }*/

}

/**
 * Class XMLElement
 *
 * @package r0b
 *
 *
 * @method DOMNodeList|XMLElement[] query(string $xpath) perform xpath query on current node
 * @method DOMNodeList|XMLElement[] queryUp(string $xpath) query parent nodes
 * @method DOMNodeList|XMLElement[] queryDown(string $xpath) query child nodes
 * @method DOMNodeList|XMLElement[] queryAll(string $xpath) query all child nodes (deep)
 *
 * @method DOMElement|XMLElement removeChild(DOMNode $oldNode)
 *
 * @property null|DOMNode|XMLElement $firstChild
 * @property null|DOMNode|XMLElement $lastChild
 * @property null|DOMNode|XMLElement $nextSibling
 * @property null|DOMNode|XMLElement $previousSibling
 * @property XMLDocument $ownerDocument
 */
class XMLElement extends DOMElement {
    /**
     * @var static::class
     */
    public $parentNode;
    /**
     * makes Document Methods available in child as traits are not an option.
     *
     * @param $method
     * @param $args
     *
     * @return mixed
     */
    public
    function __call($method, $args) {
        $args[] = $this;
        return $this->ownerDocument->{$method}(...$args);
    }

    /*
    function query($xpath) {
    return $this->ownerDocument->query($xpath,$this);
    }*/

    public
    function __get($name) {
        return $this->getAttribute($name);
    }

    public
    function __set($name, $val) {
        return $this->setAttribute($name, $val);
    }

    public
    function __isset($name) {
        return $this->hasAttribute($name);
    }

    public
    function __unset($name) {
        $this->removeAttribute($name);
    }

    public
    function __toString() {
        $attrs = [];
        /** @var DOMNode $attr */
        foreach ($this->attributes as $attr) {
            $attrs[] = "{$attr->nodeName}='{$attr->nodeValue}'";
        }
        return sprintf('<%s%s%s>%s', $this->nodeName, empty($attrs) ? '' : ' ', implode(' ', $attrs), $this->hasChildNodes() ? "</{$this->nodeName}>" : '');
    }
    public
    function getNodeDepth() {
        return substr_count($this->getNodePath(),'/')-1;
    }

    /**
     * @return static::class
     * @throws \DOMException
     */
    public
    function remove() {
        if(!$parent=$this->parentNode) throw new \DOMException('Not not in Document: '.$this->getNodePath());
        if($this===$parent->removeChild($this)) {
            return $parent;
        }
        throw new \DOMException("Could not remove node ".$this->getNodePath());
    }

    /**
     * call method $method on each item
     * returned by $this->query($qry)
     *
     * @param $qry
     * @param string|callable $method
     * @param array ...$args
     *
     * @return mixed[]
     */
    public
    function each($qry,$method='self',...$args) {
        $items=[];
        if(is_callable($method))
            foreach($this->query($qry) as $item)
                $items[]=$method($item,...$args)??$item;
        else
            foreach($this->query($qry) as $item)
                $items[]=(new invocationHelper($item))->{$method}(...$args)??$item;

        return $items;
    }

    public
    function appendTo(DOMNode$ele){
        $ele->appendChild(
            $ele->ownerDocument===$this->ownerDocument
                ?$this
                :$ele->ownerDocument->importNode($this,true)
        );
    }
    public
    function self() {
        return $this;
    }
}


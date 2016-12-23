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
    function __construct($obj) {
        $this->handle=$obj;
    }
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
 *
 * @package r0b\xml
 */
class XMLDocument extends DOMDocument
{
    const ns='xml';
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
    function __construct($xml_version = '1.0', $encoding = 'utf-8') {
        parent::__construct($xml_version, $encoding);

        $this->preserveWhiteSpace = FALSE;
        $this->namespaces=static::namespaces;

        $this->registerNodeClass(DOMElement::class, XMLElement::class);
    }

    static
    function fromString($source, $options = NULL) {
        $res = new static();
        return $res->loadXML($source, $options);
    }

    /**
     * @param      $filename
     * @param null $options
     *
     * @return bool|static
     */
    static
    function fromFile($filename, $options = NULL) {
        $res = new static();

        return $res->load((string)$filename, $options) ? $res : FALSE;
    }



    function query($xpath, DOMNode $context = NULL) {
        return $this('xpath query',''.$xpath, $context);
    }

    function queryAll($xpath, DOMNode $context = NULL) {
        echo "queryAll: " . '//' . $xpath . "\n";

        return $this->xpath('query','//' . $xpath, $context);
    }

    function queryUp($xpath, DOMNode $context = NULL) {
        return $this->xpath('query','ancestor-or-self::' . $xpath, $context);
    }

    function queryDown($xpath, DOMNode $context = NULL) {
        return $this->xpath('query','descendant-or-self::' . $xpath, $context);
    }

    /**
     * @param string[] ...$funcNames
     */
    function addPHPSelectors(...$funcNames) {
        $this("importNS php http://php.net/xpath");
        $this->xpath('registerPhpFunctions',empty($funcNames) ? NULL : $funcNames);
    }

    function importNS($prefix,$uri,string $xsdFile=null) {
        return $this->xpath('registerNamespace', $prefix, $uri)
               && $this->namespaces[$prefix]=[$uri,$xsdFile];
    }

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
    function xpath($methodName,...$args){
        isset($this->xpath)||$this->loadXpath();

        return call_user_func([$this->xpath,$methodName],...$args);
    }

    protected function loadXpath() {
        echo "reloading XPath...\n\n";
        $this->xpath = new DOMXPath ($this);
        $regSucc=true;
        foreach ($this->namespaces as $key => list($uri, $schema)) {
            $registered=$this->xpath->registerNamespace( $key, $uri);
            $regSucc=$registered?$regSucc:FALSE;
        }
        return $regSucc;

    }

}

/**
 * Class XMLElement
 *
 * @package r0b
 *
 *
 * @method DOMNodeList query(string $xpath) perform xpath query on current node
 * @method DOMNodeList queryUp(string $xpath) query parent nodes
 * @method DOMNodeList queryDown(string $xpath) query child nodes
 * @method DOMNodeList queryAll(string $xpath) query all child nodes (deep)
 *
 */
class XMLElement extends DOMElement {

    /**
     * makes Document Methods available in child as traits are not an option.
     *
     * @param $method
     * @param $args
     *
     * @return mixed
     */
    function __call($method, $args) {
        $args[] = $this;

        return call_user_func([$this->ownerDocument, $method], ...$args);
    }

    /*
    function query($xpath) {
    return $this->ownerDocument->query($xpath,$this);
    }*/

    function __get($name) {
        return $this->getAttribute($name);
    }

    function __set($name, $val) {
        return $this->setAttribute($name, $val);
    }

    function __isset($name) {
        return $this->hasAttribute($name);
    }

    function __unset($name) {
        $this->removeAttribute($name);
    }

    function __toString() {
        $attrs = [];
        /** @var DOMNode $attr */
        foreach ($this->attributes as $attr) {
            $attrs[] = "{$attr->nodeName}='{$attr->nodeValue}'";
        }

        return sprintf('<%s%s%s>%s', $this->nodeName, empty($attrs) ? '' : ' ', implode(' ', $attrs), $this->hasChildNodes() ? "</{$this->nodeName}>" : '');
    }

}


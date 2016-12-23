#!/usr/bin/env php
<?php

/*
# namespace mlu\common\functions;
use# XMLReader;
/*
inclu#de 'xml2assoc.fn.php';
/*
# #d#efine('export_namespaces',[
    'ns2'=>'wadl\\groupwise',
  'ns1'=>'wadl\\atom'
]);
*/

namespace wadl;

use XMLReader as XML;
use callByRegex,collection;

error_reporting(E_ALL/*|E_STRICT*/);

require_once ('includes/callByRegex.php');
require_once ('includes/collection.php');


/**
 * Class XMLNode
 *
 * @package wadl
 *
 * @ method static eachChild(callable $cb,...$args)
 * @property null|string $__textValue
 */
class XMLTree {
    use collection ,callByRegex {
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
    /** @var XML */
    protected        $xml;
    /** @var string|null */
    public           $tagName, $tagPrefix, $text;
    /**
     * @var static[]
     */
    public           $children=[];
    #public $attributes=[];

    /**
     * XMLTree constructor.
     *
     * the constructor is not meant to be called directly. please use the factory method.
     * @see fromUrl() the factory method
     *
     * @param XML $xml
     */
    function __construct (XML $xml) {
        $this->xml=$xml;
        $this->read();
        $this->addHandlers();
    }
    protected function addHandlers() {
        
        return $this
            ->addHandler('eachChild','each_children')
            ->addHandler('each_(.+)','each');
    }
    function __debugInfo()
    {
        return array_keys(array_filter((array)$this));
    }
    protected function xmlRead() {
        static::$curType=(static::$curType||$this->xml->read())?$this->xml->nodeType:false;
        //echo preg_replace('/(<[^>]+>).*/','\1',@$this->xml->readOuterXml())."\n";
        //echo str_replace(@$this->xml->readInnerXml(),'',@$this->xml->readOuterXml())."\n";
        return static::$curType;
    }
    protected function xmlDone () {
        $this->xml->close();
        echo "XML $this->href closed.\n";
    }

    protected function readTag() {
        $this->tagName   = $this->xml->localName;
        //echo "XML Element: $this->tagName \n";
        //$this->tagPrefix = $this->xml->prefix;
        //NAMESPACE-Analysis?
        #$this->depth     = $this->xml->depth;
        static::$curType=null;
    }
    protected function readChildren(){
        if(!$this->xml->isEmptyElement) {
            $this->xml->moveToElement();
            while ($type=$this->xmlRead()) {
                if ( $type == XML::END_ELEMENT ) break;
                if(in_array($type,static::childTypes)) {
                    $this->appendChild($this->createChild());
                }
                static::$curType=null;
            }
        }
    }

    protected function appendChild(XMLTree $child) {
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
            while ( $this->xml->moveToNextAttribute () ) {
                $prop=$this->xml->name;
                if(isset($this->$prop)) echo "WARNING: overwriting property $prop: {$this->$prop}->{$this->xml->value}!\n";
                $this->$prop = $this->xml->value;
            }
        static::$curType =false;
    }

    protected function readText() {
        static::$curType =false;
        return
            $this->__textValue=trim($this->xml->value);
    }

    protected function read() {
        # read element or hit end of file
        if (!$this->xmlRead()) return;
        $isRoot=$this->xml->depth==0;
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
        $isRoot&&$this->xmlDone();
        static::$curType=null;
        unset($this->xml);
    }
    public function __toString () {
        return (string)json_encode($this->__debugInfo());
    }
    public static function _pubs($obj) { return get_object_vars($obj);}


    static function fromUrl($url) {
        return new static(static::xmlOpen($url));
    }
    protected static function xmlOpen ($url) {
        $xml = new XML();
        $opened =
            $xml->open($url, null, LIBXML_NOBLANKS/*|LIBXML_NOCDATA*/ )
            // following is a failover for invalid certificates
            ||$xml->xml(
                file_get_contents($url, false, stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]])),
                null,
                LIBXML_NOBLANKS |LIBXML_NOCDATA
            );
        // does not seem to work, requires schema modifications
        //$xml->setSchema('xml_schemata/wadl.xsd');
        return $opened?$xml:null;
    }

    static function __main__($file='wadl_schemata/test.wadl') {

        $reader = new XML();
        $reader->open ( $file );

        $test = new static ( $reader );
        #fwrite(STDERR,print_r($test,true));
        $test->eachChild ( $fn = function (XMLTree $node ) use ( &$fn ) {
            static $depth = 0;
            echo str_repeat ( "\t" , $depth ) . " $node->tagName\n";
            $depth++;
            $node->eachChild($fn);
            $depth--;
        });
        echo $test;
    }

}

/*
 * resource[] -->path
 * |-- param[]? --> name,(type),style(template)
 * |-- method[] --> id("bezeichnung"),name("methode")
 * |   |-- request
 * |   |   |-- param[]? -->name,type,style(query)
 * |   |   |-- representation[0]? --> element?
 * |   |-- response
 * |   |   |-- representation[0]? --> element?
 * |-- resource[]?
 *
 */

/**
 * Class wadlApplication
 * @package wadl
 *
 * @method eachGrammars(callable $func)
 * @method eachInclude(callable $func)
 * @method eachResource(callable $func)
 * @method eachDoc(callable $func)
 * @method eachParam(callable $func)
 * @method eachMethod(callable $func)
 * @method eachRepresentation(callable $func)
 * @method eachRequest(callable $func)
 * @method eachResponse(callable $func)
 *
 * @method wadlApplication NamedItems() set target for append() to $NamedItem
 * @method wadlApplication ReferencedItems() set target for append() to $ReferencedItem
 */
class wadlApplication extends XMLTree {
    //use sharedProperties;

    protected
        //$Namespaces=[],
        /** @var self */
        $application;
    protected $href,$id;

    function __construct(XML $xml,string$href=null,wadlApplication$application=null) {
        // inject debug handler
        $href&&$this->href=$href;
        $this->application=$application??$this;
        //$this->sharedProperties($application??$this,'application','NamedItem','ReferencedItem');
        $this->addHandler('((?:Referenc|Nam)edItems)','collection');
        parent::__construct($xml);
        $this->addHandler('(?:append|build)(.*)','_analyze')
            ->addHandler('each([A-Z]\w+)','each')
            ->addHandler('has(?:_)?(.+)','has');
        //$this->application==$this&&$this->buildApplication();
    }
    static function fromUrl(string$url,wadlApplication$parent=null) {
        $xml = static::xmlOpen($url);
        //printf("NEW %s from url %s\n",__CLASS__,$url);
        return new static($xml,$url,$parent);
    }

    protected function createChild()
    {
        return new static($this->xml,null,$this->application);
    }


    protected function appendChild(wadlApplication$child) {
        //$this->target('NamedItem');
        ($child->id)&&$this->NamedItems()->append($child,'id');
        // try to call analyzer method for this node
        $this->{'append'.($tag=$child->tagName??'Text')}($child)
        // the analyze function could have returned something == true to skip appending
        ||($group=ucfirst($tag))&&(isset($this->$group)?($this->$group[]=$child):($this->$group=[$child]));
    }
    protected function appendInclude (wadlApplication$inc){
        $this->tagName=='grammars'||print ("WARNING: <include> found in <$this->tagName>\n");
        return $inc->href??false?false:print("WARNING: <include> without href-attribute\n");
        /* wadl:include {
             doc*,
             attribute href { xsd:anyURI },
             foreign-attribute
        } */
        //echo $inc."\n";
        //$inc->href;
    }
    protected function appendDoc (wadlApplication$doc) {
        /* wadl:doc {
            attribute xml:lang { languageTag }?,
            attribute title { text }?,
            ( text | foreign-element )*,
            foreign-attribute
        } */
        return !$doc->hasText();
    }
    protected function appendResource(wadlApplication$ressource) {
        /* wadl:resource {
            doc*,
            param*,
            (method | resource)*,
            attribute type { list {xsd:anyURI} } ?,
            attribute path { text }?,
            attribute id { xsd:token }?,
            attribute queryType { text }?,
            foreign-element,
            foreign-attribute
        } */
        in_array($this->tagName,[@resource,@resources])||print ("WARNING: <resource> found in <$this->tagName>\n");
        //($id=$ressource->id??false)&&$this->application->NamedItem[$id]=$ressource;
        //$ressource->path;
    }
    protected function appendGrammars (wadlApplication$grammars) {
        /* wadl:grammars {
            doc*,
            incl*,
            foreign-element
        } */
        $this->tagName=='application'||print("WARNING: <grammars> found in <$this->tagName>\n");
    }
    protected function appendResources(wadlApplication$res) {
        /* wadl:resources {
            doc*,
            resource+,
            attribute base { xsd:anyURI },
            foreign-attribute,
            foreign-element
        } */
        $this->tagName=='application'||print("WARNING: <Resources> found in <$this->tagName>\n");
    }
    protected function appendMethod(wadlApplication$method) {
        /*wadl:method {
            (
                (
                    attribute href { xsd:anyURI }
                ) | (
                    doc*,
                    request?,
                    response*,
                    attribute id { xsd:token }?,
                    attribute name {
                        "DELETE" | "GET" | "HEAD" | "POST" | "PUT" | xsd:token
                    }
                )
            ),
            foreign-element,
            foreign-attribute
        }*/
        $this->tagName=='resource'||print("WARNING: <Method> found in <$this->tagName>\n");
    }
    protected function appendRepresentation(wadlApplication$repr) {
        /*wadl:representation {
        (
            (
                attribute href { xsd:anyURI }
            ) | (
                doc*,
                param*,
                attribute id { xsd:token }?,
                attribute element { xsd:QName }?,
                attribute mediaType { text }?,
                attribute profile { list { xsd:anyURI} }?
            )
        ),
        foreign-attribute,
        foreign-element
    }*/
        ($repr->href??0)&&
        (isset($this->application->ReferencedItem[$repr->href])
            &&($this->application->ReferencedItem[$repr->href][]=$repr)
            ||$this->application->ReferencedItem[$repr->href]=[$repr]
        );
        in_array($this->tagName,['request','response'])||print("WARNING: <Representation> found in <$this->tagName>\n");
    }
    protected function appendRequest(wadlApplication$req) {
        /*wadl:request {
            doc*,
            param*,
            representation*,
            foreign-attribute,
            foreign-element
        }*/
        $this->tagName=='method'||print("WARNING: <Request> found in <$this->tagName>\n");
    }
    protected function appendResponse(wadlApplication$req) {
        /*wadl:response {
            doc*,
            param*,
            representation*,
            attribute status { list { xsd:int+ } }?,
            foreign-attribute,
            foreign-element
        }*/
        $this->tagName=='method'||print("WARNING: <Response> found in <$this->tagName>\n");
    }
    protected function appendParam(wadlApplication$param) {
        /*wadl:param {
            (
                (
                    attribute href { xsd:anyURI }
                ) | (
                    doc*,
                    option*,
                    link?,
                    attribute name {xsd:token },
                    attribute style {
                        "plain" | "query" | "matrix" | "header" | "template"
                    },
                    attribute id { xsd:token }?,
                    attribute type { text }?,
                    attribute default { text }?,
                    attribute path { text }?,
                    attribute required { xsd:boolean }?,
                    attribute repeating { xsd:boolean }?,
                    attribute fixed { text }?
                )
            ),
            foreign-element,
            foreign-attribute
        }*/
        //if($param->id??false) $this->NamedItems()->append($param,'id');
        in_array($this->tagName,['request','resource'])||print("WARNING: <Param> found in <$this->tagName>\n");
    }
    protected function appendText(wadlApplication$text) {
        $this->text.=$text->__textValue??'';
        return true;
    }
    protected function importHref ($uri) {

    }


    protected function has($attr) {
        return !empty($this->$attr??$this->{strtolower($attr)}??false);
    }
    protected function buildApplication() {
        echo "BUILD Application...\n";
        $this->eachGrammars([$this,'buildGrammars']);
    }
    protected function buildGrammars(wadlApplication$grammars) {
        $grammars->eachInclude(function(wadlApplication$inc){
            $target=urlHelper::followStr($this->href,$inc->href);
            echo "Including Grammar: $target\n";
            //print_r(wadlApplication::fromUrl($target));
        });
    }


    // debug...
    protected function _analyze($tag) {
        echo "INFO: Not analyzing tag <$tag>".PHP_EOL;
    }
    static function __main__($file = 'Apis/GW14.2.2/application.wadl') {
        file_exists($file)||die("Error, file not found: ".$file);
        /** @var wadlApplication $app */
        $app = static::fromUrl($file);
        $app->eachNamedItem(function(wadlApplication$itm){
            printf("%s => %s\n",$itm->id,json_encode($itm,JSON_PRETTY_PRINT));
        });
        echo $app;
        //print_r($app);
    }

    function __debugInfo()
    {
        return array_keys(array_filter((array)$this));
    }
}


/**
 * Class url
 * @package wadl
 *
 * @property string $protocol
 * @property string $remote
 * @property string $abs
 * @property string $dir
 * @property string $file
 * @property string $query
 * @property string $anchor
 *
 * @property string $requestPath
 * @property string $filePath
 *
 */
class urlHelper {
    public $href;
    //public $href,$remote,$proto,$secure,$socket,$server,$port,$local,$path,$abs,$element,$resource,$filename,$query,$anchor;
    //const names='href remote proto secure socket server port local path abs element resource filename query anchor';
    /*const matchProtocol='((?:ht|f)tp(s)?):\/\/'; //$proto, $secure
    const matchSocketRoot='(([^:\/]+)(?::([0-9]+))?)(?=\/)'; //$socket,$server,$port
    const matchDir='((\/)?(?:[^\/?#]+\/)*)?'; //$abs, $path
    const matchFilename='([^\/?#]+)?'; //$filename
    const matchQuery='(?:\?([^#]+)?)?'; //$query
    const matchAnchor='(?:#(.+)?)?'; //$anchor

    const regEx='/^('.self::matchProtocol.self::matchSocketRoot.')?((?=.)'.self::matchDir.'(('.self::matchFilename.self::matchQuery.')'.self::matchAnchor.'))?$/';
    //const regExOld='/^(((?:ht|f)tp(s)?):\/\/(([^:\/]+)(?::([0-9]+))?)(?=\/))?((?=.)((\/)?(?:[^?\/#]+\/)*)?((([^\/?#]+)?(?:\?([^#]+)?)?)(?:#(.+)?)?))?$/';
    */
    function __construct(string$href='',$analyze=false) {
        $this->href=$href;
        //$names=explode(' ',static::names);
        //printf("\n%s\n%s\n",static::regEx,static::regExOld);
        if(preg_match("`^
            (?<remote>
              (?<protocol>(?>ht|f)tp(?<secure>s)?:)?//
              (?<socket>
                  (?<server>
                    (?<ip>(?>(?>2[0-4]\d|25[0-5]|[01]?\d\d?)\.){3}(?>2[0-4]\d|25[0-5]|[01]?\d\d?))
                    |
                    (?<host>(?:\w|\w[-\w]{0,61}\w)(?:\.(?:\w|\w[-\w]{0,61}\w))*)
                  )
                  (?>:(?<port>[1-9][\d]*))?
              )?
              (?=/|$)
            )?
            (?<local>
              (?<requestPath>
                (?<filePath>
                  (?<dir>(?<abs>/)?(?>[^/?#]+/)*)?
                  (?<file>(?<base>[^/?#]+)(?>\.(?<ext>[^/?#.]+))?)?
                )?
                (?<query>\?[^\?\#]*)*
              )?
              (?<anchor>\#[^\#]*)*
            )?$`xXDA"
        ,$href,$matches)) {
            array_walk($matches,function($v,$k){is_string($k)&&$this->$k=$v;});
            $this->href=$this->remote.$this->dir.$this->file.$this->query.$this->anchor;
        }/* else {printf("No match for '%s'\n",$href);}
        if(preg_match(static::regEx,
            $href,
            $matches)
        ) {
            foreach ($matches as $n => $v) $this->{$names[$n]}=$v;
        } */else echo "Invalid URL: $href\n";
    }
    public function __get($name) {
        return $this->$name??null;
    }

    /**
     * @param string|self $target
     * @return $this|static
     */
    function follow ($target) {
        $c=static::class.'::fromStr';
        /** @var static $target */
        $target=$c($target);
        switch (true) {
            case $target->protocol: return $target;
            case $target->remote : return $c($this->protocol.$target);
            case $target->abs: return $c($this->remote.$target);
            case $target->dir:
            case $target->file: return $c($this->remote.$this->dir.$target);
            case $target->query: return $c($this->remote.$this->filePath.$target);
            case $target->anchor: return $c($this->remote.$this->requestPath.$target);
            default:
                printf("%s::%s ( '%s' ) invalid URL?\n",__CLASS__,__METHOD__,$target);
                return $this;
        }
    }

    static function followStr(string$from,string$to) {
        return (new static($from))->follow($to);
    }
    function __toString()
    {
        return $this->href;
    }
    static function fromStr(string $from) {
        return new static($from);
    }

    static function __main__ (...$argv) {
        $base='/etc/shadow';
        $next=new static($base);
        $t=function(string $str)use(&$next) {
            return "Follow $next + $str -> ".($next=$next->follow($str));
        };
        $f=function(string ...$s)use($t) {
            return implode("\n",array_map($t,$s));
        };
        printf ("%s\n%s\n",
            __METHOD__, $f(
                'http://out-sourced.net/path/to/target.wtf?asdf#wtf',
                '','?dsfsa','unallowedFilename','/safadf#sdfaf','//somewhere/else')
        );

    }
    function __debugInfo()
    {
        return array_keys(array_filter((array)$this));
    }
}

function __main__(string ...$argv) {
    printf("\n==== %s in %s\n",__METHOD__, __FILE__);
    foreach( [@XMLTree, @wadlApplication,@urlHelper] as $cls) {
        $func= __NAMESPACE__."\\$cls::__main__";
        printf("\n==== %s ( %s ) ====\n", $func, implode(' , ' , $argv));
        $func(...$argv);
        printf("\n ==== %s ( END ) ====\n\n", $func);
    };
}

(__FILE__==realpath(array_shift($argv)))&&__main__(...$argv);
//(__FILE__==realpath(array_shift($argv)))&&XMLTree::__main(...$argv);



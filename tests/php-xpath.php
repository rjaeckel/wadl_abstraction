<?php

/**
 *
 * xpath TEST'ing against gw api :D
 *
 * https://wiki.selfhtml.org/wiki/XML/XSL/XPath/Funktionen
 * https://wiki.selfhtml.org/wiki/XML/XSL/XPath
 *
 */


namespace r0b\xml;

use DOMNode, DOMDocument, DOMElement, DOMNodeList, DOMXPath;

require_once 'includes/XMLDocument.php';


/**
 * Class wadlDocument
 * @package r0b
 *
 * @method static wadlDocument fromFile($fileName,$options=null)
 *
 */
Class wadlDocument extends XMLDocument {


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
     *
     */
    const ns   ='wadl';

    const namespaces=[
        'xml'=>parent::namespaces[parent::ns],
        'wadl'=>['http://wadl.dev.java.net/2009/02','xml_schemata/wadl.xsd']
    ];

    const doc  =self::ns.':doc';
    const grm  =self::ns.':grammars';
    const res  =self::ns.':resource';
    const Res  =self::ns.':resources';
    const par  =self::ns.':param';
    const met  =self::ns.':method';
    const inc  =self::ns.':include';
    const req  =self::ns.':request';
    const resp =self::ns.':response';
    const rep  =self::ns.':representation';

    function Grammars() {
        return $this->query('wadl:grammars');
    }

    /**
     * @return XMLElement|DOMNode;
     */
    function ResourceRoot() {
        return $this('xpath query',static::Res)->item(0);
    }

    function countIDs () {
        $res=[];
        // returns DOMElement
        foreach ($this->query('//wadl:resources//*[@id]') as $item) {
            /** @var $item \DOMElement */
            if($item->getAttribute('id')=='object') {
                if($item->localName=='method') {
                    echo " + object in " .
                        $this->query('..',$item)->item(0)->getAttribute('path'). "\n";
                } else {
                    echo " else skipped $item->localName\n";
                }
            }
        }

        // returns DOMAttr
        foreach ($this->query('//@id') as $elem) @$res[$elem->value]++;
        arsort($res);
        //print_r($res);
        return $res;
    }
    protected function resourceUrl (DOMElement $of) {
        $paths=[];
        foreach ($this->queryUp('*/@path',$of) as $val)
            $paths[]=$val->value;
        return implode('/',$paths);

    }

    function getRootPaths() {
        $root=$this->ResourceRoot();
        /** @var wadlElement $res */
        $arr=[];
        foreach ($root->query(self::res) as $res) {
            $rootPath=$root->base.$res->path;
            $pathName=@array_pop(explode('/',$res->path));
            //printf (' * %s in "%s"%s',$pathName,$rootPath,PHP_EOL);
            $arr[$pathName]=$rootPath;
        }
        #asort($arr);
        #print_r($arr);


        $arr=[];
        foreach ($root->queryAll(self::res) as $res) {
            $rootPath=$root->base.$this->resourceUrl($res);
            $pathName=@array_pop(explode('/',$res->path));
            //printf (' * %s in "%s"%s',$pathName,$rootPath,PHP_EOL);
            $arr[$pathName]=$rootPath;
        }
        asort($arr);
        print_r($arr);
    }

    function mapClassMethods() {
        $root=$this->ResourceRoot();
        foreach ($root->query(self::res) as $res) {
            if (!($pathName=@array_pop(explode('/',$res->path)))||in_array($pathName,['install','node'])) continue;
            foreach ($res->queryDown(self::met) as $m) {
                printf("Method %s::%s as <%s %s>\n",$pathName,$m->id,$m->name,$this->resourceUrl($m));
            }
        }
    }

    /**
     * read out generator attribute if set
     * @return bool|array false on error or [$prefix,$uri,$value]
     */
    function getGenerator() {

        /** @var \DOMAttr $elem */
        $elem=$this->queryAll("wadl:doc/@*[local-name()='generatedBy']")->item(0);
        return $elem instanceof \DOMAttr?[$elem->prefix,$elem->namespaceURI,$elem->value]:false;
    }
}




/***
 * ini_set('assert.exception', 1);

class CustomError extends AssertionError {}

assert(false, new CustomError('Some error message'));
 */



/**
 * XML-VALIDATION AGAINST XSD
 *
 * http://php.net/manual/de/domdocument.schemavalidate.php#117995
 */



$WADL_file='wadl_schemata/gw14.2.2.wadl';

$xpath=wadlDocument::fromFile($WADL_file);

// accesses protected method..
$xpath('reloadXpath');

$xpath->ResourceRoot();

print_r( $xpath->getGenerator() );
#echo $xpath->queryAll('//@jersey:generatedBy')->length;

#$xpath->mapClassMethods();



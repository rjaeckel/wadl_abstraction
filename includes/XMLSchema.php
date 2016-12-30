<?php
/**
 * Created by PhpStorm.
 * User: r0b
 * Date: 24.12.2016
 * Time: 20:21
 */


namespace r0b\xml;

use \DOMNode;
use Net_URL2;
use r0b\xml\XMLSchema as xs;

#require_once 'XMLDocument.php';
#require_once 'xpath-helpers.php';
#require_once 'Net/URL2.php';
/*
set_include_path(implode(PATH_SEPARATOR,[
    __DIR__.'\\..',
    get_include_path()
]));
*/
/**
 * Class XMLSchema
 * @package r0b\xml
 *
 * XML-Schemata define the structure of XML-elements.
 *
 * this class tries to deal with the typical structure of these files for user's purpose
 */
class XMLSchema extends XMLDocument {

    const ns = 'xs';
    const namespaces = [
        self::ns => parent::namespaces[parent::ns]
    ];
    const schema=self::ns.':schema';
    const import=self::ns.':import';
    const element=self::ns.':element';
    const simpleType=self::ns.':simpleType';
    const simpleContent=self::ns.':simpleContent';
    const complexType=self::ns.':complexType';
    const complexContent=self::ns.':complexContent';
    const extension=self::ns.':extension';
    const restriction=self::ns.':restriction';
    const enumeration=self::ns.':enumeration';
    const attribute=self::ns.':attribute';

    /*'xs:schema xs:element xs:complexType xs:simpleType xs:simpleContent xs:extension xs:sequence
    xs:complexContent xs:attribute xs:restriction xs:enumeration */

    public
    function imports(DOMNode$context=null) {
        return $this->query(xs::import,$context);
    }

    public
    function complexTypes() {
        return $this->query(xs::complexType);
    }

    /**
     *
     * SimpleTypes define valid values for attributes
     *
     * @return \DOMNodeList|XMLElement[]
     */
    public
    function simpleTypes() {
        return $this->query(xs::simpleType);

    }
    public
    function elements() {
        return $this->query(xs::element);
    }

    public function types() {
        return $this->query('//*[self::xs:simpleType or self::xs:complexType]');
    }

}


/*
$schema = XMLSchema::fromFile('xml_schemata/xsd1.xsd');


#$schema = XMLSchema::fromFile('xml_schemata/XMLSchema.xsd');
#$schema->each('//xs:annotation','remove');


/***
 * Handle <xs:import>
 */

#$url=new Net_URL2('file://'.$schema->documentURI);

/** @var XMLElement $import * /
foreach ($schema->imports() as $import) {
    echo "IMPORT <$import->schemaLocation> INTO <$schema->documentURI>\n";
    echo $url->resolve($import->schemaLocation)."\n";
    $importSchema=XMLSchema::fromFile($url->resolve($import->schemaLocation));
    # just appending isn't actually valid :/
    #$importSchema->each('*[not(self::xs:import)]','appendTo',$import->parentNode);
    #echo $importSchema->saveXML()."\n\n";
    #$import->remove();
}

echo $schema->saveXML();

/* */


/*
foreach ($schema->query('xs:element') as $elem) {
    echo str_repeat('* ',$elem->getNodeDepth())."$elem\n";
    #elem->renderTree();
    foreach($schema->query("//*[@name='$elem->type' and not(self::xs:element)]") as $option) {
        echo str_repeat('* ',$option->getNodeDepth())."$option\n";
        #echo $option->getNodePath()."\n";
    }
}
*/
/*
$types=[];

#$schema->addPHPSelectors('mergeValues');

/** @var XMLElement $typeDef */
/*
foreach ($schema->simpleTypes() as $typeDef) {
    echo $typeDef->name.': SimpleType<'.$typeDef->firstChild->base.">\n";
    if($base=$typeDef->queryValue('concat("",xs:restriction/@base)')) {
        echo " * base: $base\n";
    }
    if($enum=$typeDef->queryValue('php:function("mergeValues","|",xs:restriction/xs:enumeration/@value)')) {
        echo " * enum: $enum\n";
        $typeDef->each('xs:restriction','remove');
    }
    if($typeDef->hasChildNodes()) {
        echo "WARNING: Not call nodes of simpleType $typeDef->name consumed: \n";
        $typeDef->renderTree();
    } else $typeDef->remove();
    $types[$typeDef->name]=$typeDef;
}
*/

/*
foreach ($schema->complexTypes() as $typeDef) {
    echo $typeDef->name.': '.$typeDef."\n";
    $types[$typeDef->name]=$typeDef;

    #$typeDef->each('.//xs:sequence','appendTo',$typeDef);

    if($extension=$typeDef->query('xs:complexContent/xs:extension')->item(0)) {
        echo " * Parent: $extension->base\n";
        #$typeDef->each('.//xs:extension','remove');
        #$typeDef->each('xs:complexContent[not(*)]','remove');
        foreach($extension->query('xs:sequence/xs:element') as $elem) {
            echo " * property: $elem->name $elem\n";
            $elem->remove();
        }
        $extension->each('xs:sequence','remove');
        $extension->each('ancestor-or-self::*[count(*)<2]','remove');
    }
    foreach($typeDef->query('xs:sequence/xs:element') as $prop) {
        echo " * property: $prop->name $prop\n";
        $prop->remove();
    }
    foreach($typeDef->query('xs:attribute') as $attr) {
        echo " * attribute: $attr->name $$attr\n";
        $attr->remove();
    }
    $typeDef->each('xs:sequence','remove');
    if($typeDef->hasChildNodes()) {
        echo "WARNING: Not call nodes of complexType $typeDef->name consumed: \n";
        $typeDef->renderTree();
    } else $typeDef->parentNode&&$typeDef->remove();
    $types[$typeDef->name]=$typeDef;
}
*/


#$schema->renderTree();



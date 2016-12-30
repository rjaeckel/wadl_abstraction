<?php

/**
 *
 * xpath TEST'ing against gw api :D
 *
 * https://wiki.selfhtml.org/wiki/XML/XSL/XPath/Funktionen
 * https://wiki.selfhtml.org/wiki/XML/XSL/XPath
 *
 */



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




set_include_path(implode(PATH_SEPARATOR,[
    __DIR__.'\\..\\includes',
    get_include_path()
]));

require_once 'WADLDocument.php';

use r0b\xml\WADLDocument;

$WADL_file='Apis/GW14.2.2/application.wadl';

$xpath=WADLDocument::fromFile($WADL_file);

// accesses protected method..
// disabled by invocation from other class
# $xpath('loadXpath');

$xpath->ResourceRoot();

#echo $xpath->queryAll('//@jersey:generatedBy')->length;

#$xpath->mapClassMethods();



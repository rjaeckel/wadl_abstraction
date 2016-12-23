<?php

namespace mlu\rest\abstractions\wadl;

require_once 'common/config.php';
require_once 'common/autoloader.php';
use mlu\common;

/**
 * generate proxy-files from wadl-file
 *
 * <p>define __devmode as true to generate dev-doc.</p>
 *
 * @author Robert Jäckel <robert.jaeckel@itz.uni-halle.de>
 * @package gwProxy
 * @subpackage api-gen
 * @category Rest-Api
 * @uses XMLReader
 * @uses xml2assoc()
 */

/**
 * WADL-Struktur:
 * innerhalb eines ressource-knotens sind die methoden-id's eindeutig
 * pro ressource (letzter teil ist string) kann es ein "construt" geben, dieser hängt dann in der wurzel
 * .../ress/ --> "create"
 * pro ressource kann es ein "update" geben, dieser hängt dann unter der nächsten ebene
 * .../ress/{param}/ --> "update"
 * daraus ließe sich jeweils die klasse ableiten
 *
 * ressourcen .../list/{element}/action/
 * sind methoden der übergeordneten resource .../list/{element}/
 * können aber auch statich über die klasse .../list/ aufgerufen werden
 * und sie sinf ggf. generisch: create ('cls',$clsdata) oder createCls($cls)
 *
 * willkommen in der funktionstheorie ;-)
 * Beispiel domain/{d}/postoffice -> create
 *   $d->create('Postoffice',$postofficeData);
 *   createPostoffice($d,$postofficeData);
 *   create('Postoffice',$d,$postofficeData);
 * Beispiel list/{type}?{querystring}
 *   list($type,$querystring)
 *   oder auch (wobei hierbei das ergenis bereits nach domain gefiltert ist)
 *   $domain->list($type,$querystring)
 *   $domain->list{$type}($querystring)
 * Dies kann man analog zur verarbeitung der IDs sehen
 *
 * man kann also die basis-funktionen anhand der template-parameter (ggf. +typ) zusammenstellen
 * $create = function($type,$data) {...}
 * $create$type=function($data) use ($create,$type) { return $create($type,$data)
 *
 * weiter verschachtelt... (beispiel fehlt)
 * $func = function($foo,$bar,$data) {...}
 * $func$foo = function ($bar,$data) use ($func,$foo) {return $func($foo,$bar,$data); }
 * $func$foo$bar = function ($data) use ($func$foo,$bar) {return $func$foo($bar,$data); }
 * wodurch $func$foo$bar ($data) möglich ist...
 *
 * man kann also auch methoden erstellen, die funktionen sind
 * und intern als ersten parameter die instanz übergeben...
 */


/**
 * @global __gwApiServer
 */

/*
 * Generate walkable tree from xml-file
 */
$reader = new \XMLReader();
//$reader->open(implode('/',array(__gwApiServer,__gwApiBase,'application.wadl')));

$reader->xml(file_get_contents(
    implode(array(__gwApiServer,__gwApiBase,'application.wadl')), false,
    stream_context_create(array('ssl'=>array ('verify_peer'=>false,'verify_peer_name'=>false)))
));
$tree = common::xml2assoc($reader);

/**
 *  filter the tree
 */
//todo: remove xml-namespaces from list by using a global...
// tags to search for
$allowedTags=explode(' ',
    'ns2:application ' //root-node
    . 'ns2:resources ' //list of browsable ressources
    . 'ns2:resource '  //resource-item, can recurse itself
    . 'ns2:param '     //variable in url|method
    . 'ns2:method '    //resource's method to interact
    . 'ns2:doc '       //documentation text
    . 'ns2:representation ' //request and response interface information
    . 'ns2:request '        //request information for validation
    . 'ns2:response '       //response information
    . 'ns2:grammars '  //list of included grammar
    . 'ns2:include');  //included grammar

// attributes to read out
$attrs=  explode(' ',
    'path ' //url relative to ancestor
    . 'name ' //request type: put|delete|post|get
    . 'type ' //type of query-string: bool|string|int
    . 'style ' //type of variable: template|query
    . 'id ' //method's name
    . 'element ' //classname of a datatype in representation
    . 'base ' //base url in application-node
    . 'mediaType ' //datatype for request
    . 'href' //link to grammar
);

// the filter function
//todo: remove xml-namespace here?
/**
 * simplify the xml-tree and filter for allowed tags and attributes
 * @global string[] $allowedTags
 * @global string[] $attrs
 * @param object $node
 * @return object
 *
 * @category helper
 * @package wadl-reader
 */
function simplify($node) {
    global $allowedTags,$attrs;
    // only continue if the tag matchtes
    if(in_array($node->tag, $allowedTags)) {
        $res = (object)null;
        // copy attributes
        foreach($attrs as $attr) if(isset($node->$attr)) $res->$attr=$node->$attr;
        // if the node is textual return it else we're dealing with subnodes...
        if(is_string (@$node->value)){
            // we're dealing with a text node. return the text
            return trim($node->value);
        } elseif(is_array(@$node->value)) {
            // we're dealing with subnodes..
            foreach($node->value as $sub) {
                // simplify the subs
                $append=simplify($sub);
                // and append them to the current node by putting it to an array of the tag name
                if(!empty($append))
                    $res->{substr($sub->tag,4)}[]=$append;
            }
        } else {
            if(isset($node->value)) {
                // should not see me...
                trigger_error("Found node not conaining string or subnodes..?".  var_export(@$node->value, 1), E_USER_NOTICE);
            } else {
                // haha! everything is ok...
            }
        }

        foreach($res as $k=>$v) {
            if(!in_array($k, array('param','method','resource'))) {
                if(is_array($v) && count($v)<2) $res->$k=$v[0];
            }
        }
        return $res;
    }
}
// generate the filtered tree
$mytree=  simplify($tree[0]);

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

/**
 * search for a url matching starting with needle
 * @category helper
 * @package wadl-reader
 *
 * @param string[] $stack the list to search in
 * @param string $needle the url to match
 * @return string|boolean the first item of stack starting with "$needle/" of false if no match found
 */
function searchParentUrl($stack,$needle) {
    foreach($stack as $itm) {
        if(stripos($needle,"$itm/")===0) return $itm;
    }
    return false;
}
/**
 * merge neighbored nodes by key-match
 * @param object[] $list list($url=>$items) that will be searched in
 *
 * <p>if a url(key) suffixes by "/" is a substring of another one in $list item-properties will be merged together</p>
 * @uses searchParentUrl()
 * @category helper
 * @package wadl-reader
 */
function mergeSubUrls ($list) {
    // get the keys in $list
    $urls = array_keys((array)$list);
    // sort them reversed to match most exactly
    rsort($urls);
    // walk the items
    foreach($urls as &$url) {
        // try to find a target an move it
        if($pNodeName=searchParentUrl($urls, $url)) {
            // remove parent's (target's) key part from current key to keep valid structure;
            // "homes/mine" will become "mine" if the parent's key is "homes" --> Notice the slash!
            $prop = substr($url, strlen($pNodeName)+1);
            // the new string is not empty (""), the item remains a subnode
            if($prop) {
                // extend target's structure if neccessary
                isset($list->$pNodeName->__subs) || $list->$pNodeName->__subs=(object)null;
                // place the item in target
                $list->$pNodeName->__subs->{$prop}=$list->$url;
            } else {
                // urls match; e.g. "home" and "home/", combine them
                foreach($list->$url as $k=>$v) {
                    $list->$pNodeName->$k=$v;
                }
            }
            // remove the moved/merged item
            unset($list->$url);
            // die url liste wird nicht aktualisiert, weil keine weitere url passen könnte

        } else {
            // no target found merge subs...
            // dueto reversed sorting of $urls there is no need to walk others
            if(isset($list->$url->__subs)) mergeSubUrls($list->$url->__subs);
        }
        // every item was moved or it's subs were merged
    }
}

/**
 * get the method's representation type
 * @param object $m method-object
 * @param string $for "request"|"response"; any others???
 * @return string|null the type's name or null if not existent
 *
 * todo: remove xml-namespace here?
 * @package wadl
 * @category helper
 */
function getRepresentation($m,$for) {
    // representation can be an array; ensure it is one
    if(!is_array(@$m->$for->representation)) {
        @$m->$for->representation=array($m->$for->representation);
    }
    // read out only the first one; the type is set in all; media types change through the items
    return isset($m->$for->representation[0]->element)?
        $m->$for->representation[0]->element
        :null;
    // todo: (?) add fallback, if the first element has no type
}
/**
 * read out wadl-params
 * @param object|array $o note which can have params as property
 * @return object object containing params as property
 *
 * @package wadl
 * @category helper
 */
function getParams($o) {
    $params=(object)null;
    if(is_array($o)) $o=(object)$o;
    if(isset($o->param)) {
        foreach ($o->param as $p) {
            // a parameter has a type, style and name
            // todo: split value or remove style
            // todo: remove xml-namespace here?
            $params->{$p->name}=$p->type." : $p->style";
        }
    }
    return $params;
}
/**
 * generate text from debug_backtrace-array
 * @param mixed[] $trace array generated by debug_backtrace
 * @return string
 *
 * @package debug
 * @category helper
 */
function backtrace($trace) {
    $res = array();
    foreach($trace as $i=>$call) {
        $call=(object)$call;
        $line = "$i\t$call->file ($call->line): $call->function (";
        $args=array();
        foreach($call->args as $arg) {
            if(is_scalar($arg)) $args[]=  var_export ($arg, 1);
            else $args[]=  '{'.gettype ($arg).'}';
        }
        $res[]=$line.implode(', ',$args).')';
    }
    return implode(PHP_EOL, $res).PHP_EOL;
}
/**
 * set an object property from outside
 * @param object $object
 * @param mixed $with value
 * @param string $as property name
 *
 * triggers a warning if the property is allready set
 * @category helper
 * @package wadl-reader
 */
function extend ($object,$with,$as) {
    if(isset($object->$as)) {
        trigger_error ("Action <$as> <".$object->$as->path.'> in Object <'.implode('/',urlStack()). '> allready defined for <'.$object->$as->path.'>', E_USER_WARNING);
        fwrite(STDERR, backtrace(debug_backtrace(null)));
    }
    $object->$as=$with;
}
/**
 * list methodnames which indicate an object
 * @return string[] list of names
 *
 */
function objectGenerics() {
    return array(
        //'replicate',
        //'rename',
        'update',
        'object',
        'delete',
    );
}
/**
 * list static methodnames that indicate a class
 * @ignore
 * @return string[]
 */
function staticGenerics() {
    return array('list','create');
}
/**
 * stack of urls used in flatten-method
 * @staticvar string[] $stack
 * @param int $level optional, read or set a specific level, remove a level if negative value
 * @param string $value optional, value to set
 * @return string[]|string|null whole stack, if all arguments omitted; one item if level was set; null if an item was removed or set
 * todo: make stack a class, with invoke- and callstatic-method
 * @package wadl-reader
 * @category helper
 */
function urlStack($level=null,$value=null) {
    static $stack; isset($stack)||$stack=array();
    switch (func_num_args()) {
        case 0:
            return $stack;
            break;
        case 1:
            if($level<0) array_pop ($stack);
            else return $stack[$level];
            break;
        case 2:
            $stack[$level]=$value;
            break;
    }
}
/**
 * get the current url in urlstack
 * @uses urlstack()
 * @return string
 */
function url() {
    return implode('/', urlStack());
}

/**
 * standadize urls and methods methods; create object($url=>methods($methodname=>$method))
 * methods may contain key "__subs" for another instance for sub-urls
 */
$fnlist = (object)array();
$lister = function ($reslist,$k,$attachTo,$basePath='') use (&$lister) {
    // resources might be alone in wadl-file, assure it's a list
    if(!is_array($reslist)) {
        $reslist=array($reslist);
    }
    // walk the resources
    foreach($reslist as $res) {
        // read template-parameters (variables in url)
        $sParam=getParams($res);
        // create default list
        $methods=(object)array('__subs'=>(object)null);
        if(isset($res->method)) {
            // walk the resource' methods
            foreach ($res->method as $m) {
                // generate basic method data
                $method=(object)array(
                    'paramStatic'=>$sParam, // template-params
                    'paramQuery'=>getParams(isset($m->request)?$m->request:array()), // query-parameters
                    'path'=>$basePath.$res->path
                    //.(substr($res->path, -1)=='/'?'':'/')
                , // url
                    'action'=>$m->name, // method put|get...
                );
                // append doc, if exists
                if(isset($m->doc)) {
                    $method->doc=  str_replace ("\n", '', $m->doc);
                }
                // find request and response types
                $method->requestType=getRepresentation($m, 'request');
                $method->responseType=getRepresentation($m, 'response');
                // store method in result-object with it's internal name (id)
                extend($methods, $method, $m->id);
            }
        }
        // if there are siblings these will be worked too
        if(isset($res->resource)) {
            $lister($res->resource,'',$methods->__subs,$res->path.(substr($res->path, -1)=='/'?'':'/'));
        }
        // clean up
        if(!count((array)$methods->__subs)) unset($methods->__subs);
        // attach result to the parent
        if($res->path) extend ($attachTo, $methods, $res->path);
    }
};

array_walk($mytree->resources->resource, $lister,$fnlist);
mergeSubUrls($fnlist);

//todo: make lambdas to functions

/**
 * get a list of root-nodes to skip/remove
 * @return string[]
 *
 * @package wadl-groupwise
 * @category helper
 */
function rootsToSkip() {
    return array('list','node');
}
/**
 * put items from one object to another removing the source's one
 * @param object $methods object to gather items from
 * @param object $target the target object
 * @param callable $testCallback optional, apply a callback ($methodObject,$methodName,$target) to each item; if the callback returns binary false nothing will happen
 * @return int number of moved items
 */
function mergeAndReduce($methods,$target,$testCallback=null) {
    $count=0;
    foreach($methods as $methodName=>$method) {
        if($methodName[0]=='_') continue;
        if(!$testCallback || false!==$testCallback($method,$methodName,$target)) {
            $count++;
            extend($target, $method, $methodName);
            unset($methods->$methodName);
        }
    }
    return $count;
}
/**
 * find out, if a set of methods represents an object
 *
 * @uses objectGenerics() to match against
 * @param type $methodList
 * @return type
 */
function representsObject($methodList) {
    $listMethods=array_keys((array)$methodList);
    return 0<count(array_intersect($listMethods, objectGenerics()));
}


/**
 * put resources within tree together to get groups/classes
 * todo: (?) bestimmte funktionen und methoden auch von eltern-objekten aus zugänglich machen..?
 * todo: vereinfachen? weitere funktionen, conditionals auflösen?
 * <p>funktioniert, sollte aber ggf noch weiter getestet werden</p>
 * @staticvar int $recursion
 * @param mixed[] $list method tree
 * @param bool|int $insideObject true, if the current resource is positioned within an object
 * @param obj $target target to move the current methods into, sometimes the source
 * @return int number of objects in current level or, if zero number of subsobjects
 *
 * @uses urlStack()
 * @uses rootsToSkip()
 * @uses representsObject()
 * @uses mergeAndReduce()
 */
function flattenMethods($list,$insideObject=0,$target=null) {
    static $recursion=-1;
    $recursion++;
    $foundObjects=0;
    foreach($list as $url => $methods) {
        $subObjects=0;
        urlStack($recursion,$url);
        if(!$target && in_array($url, rootsToSkip())) {
            unset($list->$url);
            continue;
        }
        // todo: check the order

        $urlRepresentsObject=(int)representsObject($methods);

        if(isset($methods->__subs)) {
            $subObjects=flattenMethods($methods->__subs, $urlRepresentsObject, $methods);
            if(!count((array)$methods->__subs)) {
                unset($methods->__subs);
            }
        }
        // catch static neighbours
        if(!($urlRepresentsObject||$insideObject)&&isset($methods->__subs)) {
            flattenMethods($methods->__subs, $urlRepresentsObject, $methods);
        }
        if($target) {
            if($urlRepresentsObject) {
                $methods->__isObject=true;
                if(!$insideObject) {
                    $foundObjects++;
                    // Unnested Object, move parent's methods into it
                    mergeAndReduce($target, $methods,function($method){
                        $method->__isStatic='object';
                    });
                } else {
                    // nested Object, everything is ok
                }
            } elseif($insideObject) {
                mergeAndReduce($methods, $target);
            } else {
                if($subObjects==1 && 1<count($keys=array_keys((array)$target))) {
                    $targets=array_keys((array)$methods->__subs);
                    common::logWrite(sprintf(
                        'Mapping orphan: %s into %s with methods: %s %s',
                        implode('/',urlStack()).'/..',
                        $targets[0],
                        implode(',', array_diff($keys,array('__subs'))),
                        PHP_EOL
                    ),STDERR);
                    //common::logWrite("orphan-mapping:\"".implode('/',urlStack())."/..\" target:\"$targets[0]\" methods:".  implode(',', array_diff($keys,array('__subs'))),STDERR);
                    mergeAndReduce($target, $methods->__subs->{$targets[0]},function($m){
                        $m->__isStatic='object';
                    });
                } else {
                    mergeAndReduce($methods, $target,function($method){
                        $method->__isStatic='orphan';
                    });
                }
            }
            /*} else {
                //only happens in levell 0 --> ok
                //trigger_error("Object without target (lv: $recursion): ". implode('/',urlStack()),E_USER_NOTICE);
             */
        }

        if(!count((array)$methods)) {
            unset($list->$url);
        }
    }
    $recursion--;
    urlStack(-1);
    return $foundObjects?:$subObjects;
}

flattenMethods($fnlist);

//echo json_encode($fnlist, JSON_PRETTY_PRINT, 100000);
/**
 * flatten the tree and return only filled Nodes/classes
 *
 * @param object[] $list the tree
 * @param int $level (optional) recursion level should be omitted
 * @return array[] array($url=>$methodlist)
 *
 * @uses urlStack()
 * @uses mergeAndReduce()
 * @uses cleanUpMethodName()
 * @uses findClassName()
 */
function getFilledNodes($list,$level=0) {
    $res=(object)null;
    foreach($list as $url=>$methods) {
        urlStack($level,$url);

        //todo: add some kind of method for sub-urls...
        if($hasSubs=isset($methods->__subs)) {
            mergeAndReduce($subs=getFilledNodes($methods->__subs, $level+1),$res);
        }
        $keys=array_keys((array)$methods);
        if($hasSubs) {
            $keys= array_diff($keys, array('__subs'));
        }
        if(count($keys)) {
            $myMethods=array();
            foreach($keys as $name) {
                $myMethods[cleanUpMethodName($name)?:$name]=$methods->$name;
            }
            $myMethods['__className']=($className=findClassName($myMethods, array('update','create','get'), function($m){
                return $m->requestType?:$m->responseType?:false;
            })?:(($c=count(urlStack()))>2?
                urlStack($c-2)
                :urlStack(0)))=='object'?'obj':$className;
            ksort($myMethods);
            mergeAndReduce(array($className=>(object)$myMethods), $res);
        }
    }
    urlStack(-1);
    // sort classnames on root level after picking up everything
    if($level==0) {
        $res = (array)$res;
        ksort($res);
        __devmode && printf("Generating Actions for %s ...".PHP_EOL,implode(', ', array_keys($res)));
        $res=(object)$res;
    }

    return $res;
    //return call_user_func_array('array_merge', $res);
}
/**
 * try to find a classname for a set of methods by inspecting some methods
 *
 * @param array[] $methodlist list of methods
 * @param string[] $identifiers method names to inspect
 * @param callback $callback function which inspects the method and returning a type, if possible
 * @return boolean|string the analyzed class name or false if none could be found
 */
function findClassName($methodlist,$identifiers,$callback) {
    foreach($identifiers as $methodName) {
        if(isset($methodlist[$methodName]) && $class=$callback($methodlist[$methodName])) {
            return strtolower($class);
        }
    }
    return false;
}

/**
 * modify metho names to match conventions
 *
 * @param string $name the interal method name
 * @return string|boolean the newname or false if the name keeps untouched
 */
function cleanUpMethodName($name) {
    $renames = array(
        'list'=>'getList',
        'createresource'=>'create',
        'updateclassofservice'=>'update',
        'deleteclassofservice'=>'delete',
        'createclassofservice'=>'create',
        'listclassofservice'=>'getList',
        'listclassofservicemembers'=>'listMembers',
        'addcosmember'=>'addMember',
        'removecosmember'=>'removeMember',
    );
    foreach($renames as $k=>$v) {
        if($k==strtolower($name)) {
            return $v;
        }
    }
    return false;
}


$groups = getFilledNodes($fnlist);
$xsdNamespace = __gwXsdNamespace;
$dataHead = $docStatic = $docInstance = array('<?php','','namespace '.__gwWadlNamespace.';',<<<filedoc
use mlu\\rest\\wadlProxy, mlu\\groupwise\\apiResult, $xsdNamespace;
/**
 * rest-api-proxies runtime-file
 * 
 * @author wadl-abstract.php
 * @package gwProxy
 * @subpackage api-gen
 * @category Rest-Api
 */

filedoc
);
//clear binary-directory. we will fill it again.
shell_exec('rm '.__binpath.'*');
shell_exec('rm '.__classpath.common::namespacePath(__gwWadlNamespace).'*');

/**
 * @todo initialize json-data only once by making the data static!
 */

foreach($groups as $class) {
    $data=array();
    $data[]=$docStatic[]=$docInstance[]='';
    //$data[]="/**\n * @ignore\n */";
    $data[]=$docStatic[]=$docInstance[]=<<<classdoc
/**
 * dynamic abstraction for gw-class $class->__className
 * 
 * <p>this class is auto-generated and may change with wadl-change.</p>
 * 
 * @author wadl-abstract.php
 * @package gwProxy
 * @subpackage api-gen
 * @category Rest-Api
 *
classdoc;
    // methods-header
    foreach($class as $mName => $mData) {
        // generate static ide-method definitions for phpstorm
        if($mName[0]=='_') { continue; }
        if($mData->responseType=='list')$mData->responseType.='Result';
        $oneliner = " * @method static apiResult|".($mData->responseType?'xsd\\'.$mData->responseType:'mixed').
            " $mName ( ".($mData->requestType?'xsd\\'.$mData->requestType:'mixed').' $data=null ,string $queryString=null ) '.
            "<p>request: $mData->action $mData->path</p>";
        foreach ($mData->paramStatic as $n=>$v) {
            $oneliner.="<p>template-var: $n => $v</p>";
        }
        foreach ($mData->paramQuery as $n=>$v) {
            $oneliner.="<p>query-String: $n => $v</p>";
        }
        $data[]=$oneliner;
    }
    $data[]=$docStatic[]=$docInstance[]=' */';
    // class header
    $data[]=$docStatic[]=$docInstance[]="class $class->__className extends wadlProxy { ";
    // constructor for runtime-file
    $data[]="\t/**\n\t * @internal\n\t */";
    $data[]="\tpublic function __construct() {";
    //$data[]='parent::__construct();';
    $data[]="\t\t\$this->methods=json_decode('".str_replace('\'','\\\'',json_encode($class))."');";
    $data[]="\t}";
    // walk methods
    $linkTarget=gwShellBin;
    $linkPlace = __binpath;
    foreach ($class as $mName=>$mData) {
        if($mName[0]=='_') { continue; }

        $symlink = /*$linkPlace.*/$class->__className.'.'.$mName;
        chdir($linkPlace);
        //fwrite(STDOUT,"in ".getcwd().": ln -fs ../$linkTarget $symlink\n");
        $err = trim(`ln -fs ../$linkTarget $symlink`);
        chdir ('..');
        common::logWrite(sprintf("Creating symlink %s: %s %s",$symlink,$err?:'ok',__devmode||$err?PHP_EOL:"\r"));
        // paramStatic paramQuery path action requestType responseType doc
        $data[]=$docStatic[]=$docInstance[]="\t/**";
        if(isset($mData->doc)) {
            $data[]=$docStatic[]=$docInstance[]="\t * ".$mData->doc;
        }
        $data[]="\t * @internal";
        $data[]=$docStatic[]=$docInstance[]="\t *";
        //$docStatic[]=               " * @param string[] \$tplVars associative array of template vars and their values";
        $data[]=$docStatic[]=$docInstance[]="\t * @param apiResult|".($mData->requestType?'xsd\\'.$mData->requestType:'mixed')." \$data user data; may repeat which will result in a merge";
        $data[]=$docStatic[]=$docInstance[]="\t * @param string \$queryString a whole query-string or one part like var=value; may repeat";
        $data[]=$docStatic[]=$docInstance[]="\t * @return apiResult|".($mData->responseType?'xsd\\'.$mData->responseType:'mixed');
        $data[]=$docStatic[]=$docInstance[]="\t *";
        $data[]=$docStatic[]=$docInstance[]="\t * <p>request: $mData->action $mData->path</p>";
        foreach ($mData->paramStatic as $n=>$v) {
            $data[]=$docStatic[]=$docInstance[]="\t * <p>template-var: $n => $v</p>";
        }
        foreach ($mData->paramQuery as $n=>$v) {
            $data[]=$docStatic[]=$docInstance[]="\t * <p>query-String: $n => $v</p>";
        }
        $data[]=$docStatic[]=$docInstance[]="\t */";
        $data[]="\tprotected function _$mName (\$data=null,\$queryString=null) { ";
        $docInstance[]="\tpublic function $mName (\$data=null,\$queryString=null) {}";
        $docStatic[]="\tpublic static function $mName (\$data=null,\$queryString=null) {}";
        $data[]="\t\treturn \$this->doRequest ('$mName',\$data,\$queryString);";
        $data[]="\t}";
    }
    $data[]=$docStatic[]=$docInstance[]="}";
    common::write2file(
        __classpath.common::namespacePath(__gwWadlNamespace).$class->__className.'.cls.php',
        implode(PHP_EOL,$dataHead),implode(PHP_EOL,$data)
    );
}

//file_put_contents(__root.'classes/gw-proxies.php', implode(PHP_EOL, $data));
//file_put_contents($devDocOutputDirectory.'gw-proxy-instance.php', implode(PHP_EOL, $docInstance));
//file_put_contents(__devDocOutputDirectory.'gw-proxy-static.php', implode(PHP_EOL, $docStatic));


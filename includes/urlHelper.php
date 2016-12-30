<?php
/**
 * Created by PhpStorm.
 * User: r0b
 * Date: 26.12.2016
 * Time: 21:58
 */

namespace r0b;



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
    public
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

    public
    function __get($name) {
        return $this->$name??null;
    }

    /**
     * @param string|self $target
     * @return $this|static
     */
    public
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
    public
    static
    function followStr(string$from,string$to) {
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
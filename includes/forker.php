<?php

/**
 * Created by PhpStorm.
 * User: r0b
 * Date: 20.04.16
 * Time: 00:18
 */
trait forker
{
    protected $handle ;

    function read () {

    }
    function create() {

    }
    function write() {

    }
    function handle(callable $h, ...$more) {

    }
    function exec(string $cmd,&$out,&$in=null,$err=STDOUT,...$handles) : string {
        
    }
}
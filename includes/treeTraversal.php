<?php

/**
 * Created by PhpStorm.
 * User: r0b
 * Date: 30.10.16
 * Time: 01:54
 */
trait chain {
    protected function isRoot() {
        return empty($this->parent());
    }
    protected function parent() {
        return false;
    }
}
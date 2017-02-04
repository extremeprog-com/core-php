<?php

class UtilDecorator {

    /** @var  Closure */
    protected $closure;

    function __construct($object, $closure) {
        $this->closure = $closure->bindTo($object);
    }

    function __call($method, $args) {
        $closure = $this->closure;
        return $closure($method, $args);
    }
}
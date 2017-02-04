<?php

abstract class API_SecurityCheck extends _OS\Request {

    const READ  = 1;
    const WRITE = 2;

    function __construct($Object, $operation = self::READ) {
    	if (!isset(class_uses($Object)['DataModel'])) {
    		throw new Exception($Object . ' does not use DataModel');
    	}
        $this->class = get_class($Object);
        $this->operation = $operation;
        $this->Object = $Object;
    }

}
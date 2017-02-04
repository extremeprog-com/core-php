<?php

class DataModel_ResolveObject extends \_OS\Request {

    function __construct($_self) {
        list($this->prefix, $this->id) = explode('-', $_self , 2);
    }

} 
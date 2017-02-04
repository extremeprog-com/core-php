<?php

class DataModel_SaveRequest extends \_OS\Request {

    function __construct($Object) {
        $this->class = get_class($Object);
        $this->Object = $Object;
    }

} 
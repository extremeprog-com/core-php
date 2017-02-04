<?php

class Eq {

    function __construct($value) {
        $this->value = $value;
    }

    function check($value) {
        return $this->value == $value;
    }
}
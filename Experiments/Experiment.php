<?php

abstract class Experiment {

    static function isOn() {
        return true;
    }

    static function isTestGroup($ident) {
        return hexdec(substr(md5($ident.static::class),2,4))%2;
    }

} 
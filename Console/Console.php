<?php

class Console {

    static $end_enter = true;

    static function write($string) {
        $string = strtr($string, ["\r" => ""]);

        if(substr($string, -1, 1) === "\n") {
            self::$end_enter = true;
        } else {
            self::$end_enter = false;
        }
        echo $string;
    }

    static function writeLn($string = '', $prefix = '') {
        $string = strtr($string, ["\r" => ""]);

        if(!self::$end_enter) {
            echo "\n";
        }

        if(substr($string, -1, 1) === "\n") {
            $string = substr($string, 0, -1);
        }

        foreach(explode("\n", $string) as $_s) {
            if($_s) {
                $_s = $prefix.$_s;
            }
            echo $_s."\n";
        }

        self::$end_enter = true;
    }

}
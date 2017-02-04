<?php

interface _OSTaskIf {

    static function is_possible();
    static function make_possible();
    static function run();
    static function been_run();


}
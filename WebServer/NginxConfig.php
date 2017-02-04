<?php

define('NGINX_DOCUMENT_ROOT', getenv('PROJECTENV').'/var/www');

class NginxConfig {

    public $path = NGINX_DOCUMENT_ROOT;

    public $definitions = array();

    public $sections = array();

}
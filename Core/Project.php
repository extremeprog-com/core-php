<?php

class Project {

    var $name;
    var $base;
    var $domain;

    function __construct($name) {

        $this->name = $name;
        $this->base = self::getConfig($name)['base'];
        $this->domain = self::getConfig($name)['domain'];
    }

    public static function get($name) {
        return new self($name);
    }

    public static function getCurrent() {
        return new self(PROJECT);
    }

    public static function getConfig($username = '') {

        if(!$username) {
            $username = getenv('USER');
        }
        $configs = FileAPI::getJSON(PATH_WORKDIR.'/domain/projects.json');
        if(!$configs) {
            $configs = [
                "*" => [
                    "base" => "prod@localhost",
                    "domain" => PROJECT.".lo",
                    "multirole" => "true"
                ]
            ];
            FileAPI::putJSON(PATH_WORKDIR.'/domain/projects.json', $configs);
        }
        if(!$config = isset($configs[getenv('USER')])?$configs[getenv('USER')]:$configs['*']) {
            throw new Exception("Settings not found for user \"$username\" in domain/projects.json");
        }
        return $config;
    }

    public static function getConfigKey($key) {
        $config = self::getConfig();
        if(!array_key_exists($key, $config)) {
            return null;
        } else {
            return $config[$key];
        }
    }

}
<?php

namespace _OS;

class Autoloader {
    
    static $actual = false;
    static $map = array();
    
    const AUTOLOAD_MAP_DIR = './';
    const AUTOLOAD_MAP_FILE = 'tmp/php_autoload_map.json';
    
    static function preinit() {
        self::initAutoload();
    }
    
    static function initAutoload() {
        spl_autoload_register(function($class) {
            \_OS\Autoloader::loadClass($class);
        });
    }

    static function loadClass($class) {

        if(!self::$map) {
            self::_loadMap();
        }

        if(!isset(self::$map[$class]) || !file_exists(Autoloader::$map[$class]) ) {
            if(!self::$actual) {
                self::generateMap();
            }
        }

        if(isset(self::$map[$class])) {
            require_once(self::$map[$class]);
        }
    }

    static function loadAll() {
        // вызывается из внешнего класса, поэтому карта уже актуальна
        foreach(self::$map as $class => $file) {
            // загружаем классы
            class_exists($class);
        }
    }

    static function generateMap($save = true) {
        
        $php_files = explode("\n", `find -L .| grep '\.php'`);
        foreach($php_files as $php_file) {
            $php_file = trim($php_file);
            if(!$php_file)
                continue;
            $class_name = substr(basename($php_file), 0, -4);
            $php_file_contents = file_get_contents($php_file);
            if(preg_match("/(class|interface|trait) +$class_name/", $php_file_contents)) {
                if(preg_match("/\n *namespace +\\\\?([a-zA-Z0-9_\\\\]+)/", $php_file_contents, $matches)) {
                    $class_name = $matches[1]."\\".$class_name;
                }
                self::$map[$class_name] = substr($php_file, 2);
            }
        }
        self::$actual = true;
        
        if($save) {
            self::_saveMap();
        }
    }
    
    protected static function _saveMap() {
        file_put_contents(self::AUTOLOAD_MAP_DIR. "/". self::AUTOLOAD_MAP_FILE, json_encode(self::$map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    
    protected static function _loadMap() {
        
        try {
            if(file_exists(self::AUTOLOAD_MAP_DIR. "/". self::AUTOLOAD_MAP_FILE))
                $map = json_decode(file_get_contents(self::AUTOLOAD_MAP_DIR. "/". self::AUTOLOAD_MAP_FILE));
        } catch(\Exception $e) { }
        
        if(isset($map) && is_array($map) && $map) {
            self::$map = $map;
        }
    }
}

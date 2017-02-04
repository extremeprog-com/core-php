<?php

class StorageKeyDataFile {
    
    protected $backend, $instance, $key, $file, $on_empty;
    
    public function __construct($instance, $key, $on_empty) {
        $this->instance = $instance;
        $this->key = $key;
        $this->file = PATH_DATA."/".$this->instance."/".$this->key.".json";
        $this->on_empty = $on_empty;
    }
    
    static function getInstance($instance, $key, $on_empty = null) {
        return new self($instance, $key, $on_empty);
    }

    public function get() {
        if(!file_exists($this->file))
            return $this->on_empty;
        return FileAPI::getJSON($this->file);
    }

    public function set($value) {
        FileAPI::putJSON($this->file, $value);
    }
    
}
<?php

class StorageKeyRedisListAsMCQ {
    
    protected $backend, $instance, $key;
    
    public function __construct($instance, $key) {
        $this->instance = $instance;
        $this->key = $key;
    }
    
    static function getInstance($instance, $key) {
        return new self($instance, $key);
    }

    public function length() {
        return ConnectionManager::getRedisFor($this->instance)->lLen($this->key);
    }

    public function set($val) {
        return ConnectionManager::getRedisFor($this->instance)->lPush($this->key, $val);
    }
    
    public function get() {
        return ConnectionManager::getRedisFor($this->instance)->lPop($this->key);
    }

}
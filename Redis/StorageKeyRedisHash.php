<?php

class StorageKeyRedisHash {
    
    protected $backend, $instance, $key;
    
    public function __construct($instance, $key) {
        $this->instance = $instance;
        $this->key = $key;
    }
    
    static function getInstance($instance, $key) {
        return new self($instance, $key);
    }

    public function set($fieldName, $val) {
        return ConnectionManager::getRedisFor($this->instance)->hSet($this->key, $fieldName, $val);
    }

    /**
     * @param array $fields_values array(fieldName=>$value, ...)
     * @return mixed
     */
    public function multiSet($fields_values){
        return ConnectionManager::getRedisFor($this->instance)->hMset($this->key, $fields_values);
    }

    public function get($fieldName) {
        return ConnectionManager::getRedisFor($this->instance)->hGet($this->key, $fieldName);
    }
    public function exists($fieldName) {
        return ConnectionManager::getRedisFor($this->instance)->hExists($this->key, $fieldName);
    }

    public function multiGet($fields){
        return ConnectionManager::getRedisFor($this->instance)->hmGet($this->key, $fields);
    }

    public function getAll() {
        return ConnectionManager::getRedisFor($this->instance)->hGetAll($this->key);
    }

    public function getAllKeys() {
        return ConnectionManager::getRedisFor($this->instance)->hKeys($this->key);
    }

    public function flush() {
        return ConnectionManager::getRedisFor($this->instance)->delete($this->key);
    }

    public function del($fieldName){
        return ConnectionManager::getRedisFor($this->instance)->hDel($this->key, $fieldName);
    }

    public function count(){
        return ConnectionManager::getRedisFor($this->instance)->hLen($this->key);
    }

    public function incr($fieldName, $value = 1){
        return ConnectionManager::getRedisFor($this->instance)->hIncrBy($this->key, $fieldName, $value);
    }

}
<?php

class StorageKeyRedisSerializedHash {
    
    protected $backend, $instance, $key;
    
    public function __construct($instance, $key) {
        $this->instance = $instance;
        $this->key = $key;
    }
    
    static function getInstance($instance, $key) {
        return new self($instance, $key);
    }

    public function get($field) {
        if($serialized = ConnectionManager::getRedisFor($this->instance)->hGet($this->key, $field)) {
            try{
                return json_decode($serialized, true);
            } catch(Exception $e) { }
        }
    }

    public function getAll() {
        if($arr = ConnectionManager::getRedisFor($this->instance)->hGetAll($this->key)) {
            try{
                foreach($arr as $k => $item) {
                    $arr[$k] = json_decode($item, true);
                }
                return $arr;
            } catch(Exception $e) { }
        }
    }

    public function set($field, $value) {
         return ConnectionManager::getRedisFor($this->instance)->hSet($this->key, $field, json_encode($value));
    }
    
    public function multiGet($fields){
        if(!$fields)
            return array();
        
        if($result = ConnectionManager::getRedisFor($this->instance)->hmGet($this->key, $fields))
            foreach($result as $key=>&$val)
                $val = json_decode($val,true);
        
        return $result;
        
    }

    public function delete($fieldName){
        return ConnectionManager::getRedisFor($this->instance)->hDel($this->key, $fieldName);
    }

    public function flush() {
        return ConnectionManager::getRedisFor($this->instance)->delete($this->key);
    }
}
<?php

class StorageKeyRedisSerializedValue {
    
    protected $backend, $instance, $key, $valueIfNotExists;
    
    public function __construct(RedisClient $instance, $key, $valueIfNotExists = null) {
        $this->instance = $instance;
        $this->key = $key;
        $this->valueIfNotExists = $valueIfNotExists;
    }
    
    static function getInstance($instance, $key) {
        return new self($instance, $key);
    }

    public function get() {
        if($serialized = $this->instance->get($this->key)) {
            try {
                return json_decode($serialized, true);
            } catch(Exception $e) {
                return $this->valueIfNotExists;
            }
        } else {
            return $this->valueIfNotExists;
        }
    }

    public function set($value) {
         return $this->instance->set($this->key, json_encode($value));
    }
    
    public function delete() {
         return $this->instance->delete($this->key);
    }

}
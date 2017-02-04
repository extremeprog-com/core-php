<?php

class StorageKeyRedisList {
    
    protected $backend, $instance, $key;
    
    public function __construct(RedisClient $instance, $key) {
        $this->instance = $instance;
        $this->key = $key;
    }
    
    static function getInstance($instance, $key) {
        return new self($instance, $key);
    }

    public function length() {
        return $this->instance->lLen($this->key);
    }

    public function blPop($timeout) {
        return $this->instance->blPop($this->key, $timeout);
    }

    public function push($val) {
        return $this->instance->lPush($this->key, $val);
    }
    
    public function pop() {
        return $this->instance->lPop($this->key);
    }

    public function pushLeft($val) {
        return $this->instance->lLPush($this->key, $val);
    }

    public function select($from, $to) {
        return $this->instance->lGetRange($this->key, $from, $to);
    }

    public function trim($from, $to) {
        return $this->instance->lTrim($this->key, $from, $to);
    }

    public function delete() {
        return $this->instance->delete($this->key);
    }

}
<?php

class StorageKeyRedisValue {
    
    protected $backend, $instance, $key;
    
    public function __construct(RedisClient $instance, $key) {
        $this->instance = $instance;
        $this->key = $key;
    }
    
    static function getInstance($instance, $key) {
        return new self($instance, $key);
    }

    public function get() {
         return $this->instance->get($this->key);
    }

    public function set($value) {
         return $this->instance->set($this->key, $value);
    }

    public function expire($time) {
         return $this->instance->expire($this->key, $time);
    }

    public function setnx($value) {
         return $this->instance->setnx($this->key, $value);
    }

    public function getset($value) {
         return $this->instance->getset($this->key, $value);
    }

    public function incr($value = 1) {
         return $this->instance->incr($this->key, $value);
    }

    public function getBit($bit) {
         return $this->instance->getBit($this->key, $bit);
    }

    public function setBit($bit, $val) {
         return $this->instance->setBit($this->key, $bit, $val);
    }

    public function getRange($from, $to) {
         return $this->instance->getRange($this->key, $from, $to);
    }

    public function setRange($offset, $value) {
         return $this->instance->setRange($this->key, $offset, $value);
    }

    public function watch() {
        return $this->instance->watch($this->key);
    }

    public function discard() {
        return $this->instance->discard();
    }

    public function multi() {
        return $this->instance->multi();
    }

    public function exec() {
        return $this->instance->exec();
    }
}
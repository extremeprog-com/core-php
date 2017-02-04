<?php

class StorageKeyRedisSet {
    
    protected $backend, $instance, $key;
    
    public function __construct($instance, $key) {
        $this->instance = $instance;
        $this->key = $key;
    }
    
    static function getInstance($instance, $key) {
        return new self($instance, $key);
    }

    public function sAdd($member) {
         return ConnectionManager::getRedisFor($this->instance)->sAdd($this->key, $member);
    }

    public function sRemove($member) {
         return ConnectionManager::getRedisFor($this->instance)->sRemove($this->key, $member);
    }

    public function sIsMember($member) {
        return ConnectionManager::getRedisFor($this->instance)->sIsMember($this->key, $member);
    }

    public function sCard() {
        return ConnectionManager::getRedisFor($this->instance)->sCard($this->key);
    }

    public function sMembers() {
        return ConnectionManager::getRedisFor($this->instance)->sMembers($this->key);
    }

    public function delete() {
        return ConnectionManager::getRedisFor($this->instance)->delete($this->key);
    }

}
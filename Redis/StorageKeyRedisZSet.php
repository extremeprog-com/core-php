<?php

class StorageKeyRedisZSet {
    
    protected $backend, $key;
    /** @var RedisClient */
    protected $instance;
    
    public function __construct($instance, $key) {
        $this->instance = $instance;
        $this->key = $key;
    }
    
    static function getInstance($instance, $key) {
        return new self($instance, $key);
    }

    public function selectAsc($offset, $limit, $scores_in_vals = false) {
         return $this->instance->zRange($this->key, $offset, $offset + $limit - 1, $scores_in_vals);
    }

    public function selectAscWithScores($offset, $limit) {
         return $this->instance->zRange($this->key, $offset, $offset + $limit - 1, true);
    }

    public function getFirst() {
        $items = $this->instance->zRange($this->key, 0, 0);
        return current($items);
    }

    public function getLast() {
        $items = $this->instance->zReverseRange($this->key, 0, 0);
        return current($items);
    }

    public function getMinScore() {
        $key_to_score = $this->instance->zRange($this->key, 0, 0, true);
        return current($key_to_score);
    }

    public function getMaxScore() {
        $key_to_score = $this->instance->zReverseRange($this->key, 0, 0, true);
        return current($key_to_score);
    }

    public function selectDesc($offset, $limit, $scores_in_vals = false) {
        return $this->instance->zReverseRange($this->key, $offset, $offset + $limit - 1, $scores_in_vals);
    }

    public function selectDescWithScores($offset, $limit) {
        return $this->instance->zReverseRange($this->key, $offset, $offset + $limit - 1, true);
    }

    public function selectByScoreAsc($min_score, $max_score, $offset = null, $limit = null) {
        return $this->instance->zRangeByScore($this->key, $min_score, $max_score, $offset, $limit, false);
    }

    public function selectByScoreAscWithScores($min_score, $max_score, $offset = null, $limit = null) {
        return $this->instance->zRangeByScore($this->key, $min_score, $max_score, $offset, $limit, true);
    }

    public function selectByScoreDesc($max_score, $min_score, $offset = null, $limit = null) {
        return $this->instance->zRevRangeByScore($this->key, $max_score, $min_score, $offset, $limit, false);
    }

    public function selectByScoreDescWithScores($max_score, $min_score, $offset = null, $limit = null) {
        return $this->instance->zRevRangeByScore($this->key, $max_score, $min_score, $offset, $limit, true);
    }

    public function insert($key, $weight) {
        return $this->instance->zAdd($this->key, $weight, $key);
    }

    public function incr($key, $weight = 1) {
        return $this->instance->zIncrBy($this->key, $weight, $key);
    }

    public function delete($member){
        return $this->instance->zDelete($this->key, $member);
    }

    public function deleteRangeByScore($start, $end) {
        return $this->instance->zRemRangeByScore($this->key, $start, $end);
    }

    public function flush() {
        return $this->instance->delete($this->key);
    }

    public function score($member){
        return $this->instance->zScore($this->key, $member);
    }


    /**
     * мощность множества
     * @return int
     */
    public function getSize(){
        return $this->instance->zCard($this->key);
    }

}
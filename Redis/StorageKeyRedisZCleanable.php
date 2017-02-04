<?php

class StorageKeyRedisZCleanable {
    
    protected $backend, $instance, $key;
    
    public function __construct($instance, $key, $clean_period) {
        $this->instance = $instance;
        $this->key = $key;
        $this->clean_period = $clean_period;
    }
    
    static function getInstance($instance, $key, $clean_period) {
        return new self($instance, $key, $clean_period);
    }

    public function selectAsc($offset, $limit, $scores_in_vals = false) {
         return ConnectionManager::getRedisFor($this->instance)->zRange($this->key, $offset, $offset + $limit - 1, $scores_in_vals);
    }

    public function selectDesc($offset, $limit, $scores_in_vals = false) {
        return ConnectionManager::getRedisFor($this->instance)->zReverseRange($this->key, $offset, $offset + $limit - 1, $scores_in_vals);
    }

    public function selectByScoreAsc($min_score, $max_score, $offset = 0, $limit = 0, $scores_in_vals = false) {
        return ConnectionManager::getRedisFor($this->instance)->zRangeByScore($this->key, $min_score, $max_score, $offset, $limit, $scores_in_vals);
    }

    public function selectByScoreDesc($min_score, $max_score, $scores_in_vals = false) {
        return ConnectionManager::getRedisFor($this->instance)->zReverseRangeByScore($this->key, $min_score, $max_score, $scores_in_vals);
    }

    public function exists($key) {
        return ConnectionManager::getRedisFor($this->instance)->exists($key);
    }


    public function incr($key, $weight = 1) {
        ConnectionManager::getRedisFor($this->instance)->lLPush('_cleanlist'.$this->key,time().' '.$key.'+'.$weight);
        ConnectionManager::getRedisFor($this->instance)->zIncrBy($this->key,$weight, $key);

        $this->_cleanup();
    }

    protected function _cleanup() {

        $timeBound = time() - TimelineModel::secondsInPeriod($this->clean_period);

        // проверим, что элемент не потерял актуальность

        while($lastElement = ConnectionManager::getRedisFor($this->instance)->rPop('_cleanlist'.$this->key,-1,-1)) {
            list($time,$key,$value) = array_values($this->_parseListElement($lastElement));
            if($timeBound <= $time)
                break;

            $score = ConnectionManager::getRedisFor($this->instance)->zIncrBy($this->key,$key,-$value);
            if($score<=0){
                ConnectionManager::getRedisFor($this->instance)->zDelete($this->key,$key);
                continue;
            }

        }

        if($lastElement)
            ConnectionManager::getRedisFor($this->instance)->lRPush($this->key, $lastElement);

    }

    protected function _parseListElement($string){
        preg_match('/^([0-9]+) (.*)(\+|\-)([0-9]+)$/',$string,$matches);
        return array(
            'time' => $matches[1],
            'key' => $matches[2],
            'value' => $matches[3].$matches[4],
        );
    }

}
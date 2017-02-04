<?php

class MonitorTimeBenchmark {
    
    public $name;
    public $time_start;
    public $string = '';

    public function __construct($name) {
        $this->name = $name;
        $this->time_start = microtime(true);
    }

    public function __destruct() {
        $dt = microtime(true) - $this->time_start;
        
        list($class, $method) = explode('::', $this->name, 2);
        
        Monitor()->timer($this->name, $dt);
        
    }

    public function add_data($string) {
        $this->string.= ' '.$string;
    }

    
}

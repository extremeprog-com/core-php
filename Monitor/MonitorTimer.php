<?php

class MonitorTimer {
    
    protected $events = array();
    protected $timers = array();
    protected $counters = array();
    
    protected function __construct(){}
    
    /** 
     * @static
     * @return Monitor
     */
    static function getInstance() {
        return new self();
    }
    
    function event($section = null, $name, $event = 'count', $count = 1) {
        Stat::writeStat($section?:App()->getMode(), $name, $event, null, array(), $count);
    }
    
    function timer($name, $timer) {
        if($timer>0){
            if($timer<1) {
                $factor = round(log($timer,10));

                if($factor<-4)
                    $factor = -4;
                
                $x = pow(10,$factor);
            } else {
                $factor = round(log($timer,2));
                $x = pow(2,$factor);
            }
            
        } elseif($timer==0) {
            $x = 0;
        } else {
            $x = '<0';
        }
       
        Stat::writeStat('carao', $name, array($x."_sec", 'count'));
    }

}

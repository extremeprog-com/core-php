<?php

class WorkSession {
    
    static $instances = array();
    public $resources = array();
    public $callbacks = array();
    
    protected function __construct() {}
    
    static function start() {
        $WorkSession = new WorkSession;
        self::$instances[] = $WorkSession;
        FireEvent(new WorkSession_Start($WorkSession));
    }
    
    static function end() {
        $WorkSession = end(self::$instances);
        while($WorkSession->callbacks) {
            $callback = array_shift($WorkSession->callbacks);
            try{
                call_user_func($callback);
            } catch(Exception $e) {
                Logger()->error($e,__CLASS__);
            }
        }
        FireEvent(new WorkSession_End($WorkSession));
        array_pop(self::$instances);
    }

    /**
     * @static
     * @return WorkSession
     */
    static function get() {
        if(!$WorkSession = end(self::$instances))
            return false;
        return $WorkSession;
    }
    
}

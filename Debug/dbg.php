<?php

date_default_timezone_set('Europe/Moscow');

class dbg {
    
    static $i;
    static $o;

    function __set($name, $value) {
        $trace = debug_backtrace();
        $line = "[".date("Y-m-d H:i:s").substr(floatval(microtime()),1,4)."] dbg on file ".$trace[0]['file']." line ".$trace[0]['line'].":\n";
        if($name=='json')
            echo "$line\n".json_encode($value, JSON_PRETTY_PRINT)."\n";
        else if($name=='html')
            echo "$line\n".nl2br(htmlspecialchars($value))."\n";
        else if($name=='log')
            Logger()->debug("$line\n".var_export($value, true), __CLASS__);
        else if($name=='skype')
            Skype::sendMessage("$line\n".print_r($value, true));
        else{
            echo "$line\n";
            var_dump($value);
        }
    }
    
}

dbg::$i = dbg::$o = new dbg();

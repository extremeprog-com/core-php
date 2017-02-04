<?php

class Stat {
    
    const QUANTIZATION_2HOURS = '2hours';
    const QUANTIZATION_DAILY  = 'daily';
    const QUANTIZATION_15MIN  = '15min';
    const QUANTIZATION_1MIN  = '1min';

    static $quantization = array(
        self::QUANTIZATION_1MIN => array(
            'start_date' => '2013-12-12 00:00:00',
            'seconds' => 60,
            'write' => true,
        ),
        self::QUANTIZATION_15MIN => array(
            'start_date' => '2013-12-12 00:00:00',
            'seconds' => 900,
            'write' => false,
        ),
        self::QUANTIZATION_2HOURS => array(
            'start_date' => '2013-12-12 05:00:00',
            'seconds' => 7200,
            'write' => false,
        ),
        // начало отсчета дневной статистики - в 5 часов утра
        // 5 часов утра - наш суточный минимум
        self::QUANTIZATION_DAILY => array(
            'start_date' => '2011-12-12  05:00:00',
            'seconds' => 86400,
            'write' => false,
        ),
    );

    public static function storageQueue() {
        return new StorageKeyRedisList(new RedisClient(['host' => 'dev.widget.mediastorage.ru', 'port' => 34587]), __METHOD__);
    }

    public static function storageStatArchive() {
        return new RedisClient(['host' => 'dev.widget.mediastorage.ru', 'port' => 34587]);
    }

    public static function writeStat($section, $objects, $event, $time = null, $group = array(), $count = 1) {

        if(!isset(WorkSession::get()->callbacks[__CLASS__])) {
            WorkSession::get()->resources[__CLASS__] = array();
            WorkSession::get()->callbacks[__CLASS__] = function() {
                Stat::storageQueue()->push(json_encode(WorkSession::get()->resources[__CLASS__]));
            };
        }

        while(!is_array_of_arrays($objects))
            $objects = array($objects);
        
        if(!$time)
            $time = time();
        
        foreach(is_array($event)?$event:array($event) as $event) 
            foreach($objects as $object) {

                $quantizations = self::$quantization;

                foreach($quantizations as $quantization=>$params) {
                    $key = self::key($section, $object, $event, $group, $quantization);
                    $pos = self::getPosByTime($time, $params);

                    if(!isset(WorkSession::get()->resources[__CLASS__][$key]))
                        WorkSession::get()->resources[__CLASS__][$key] = array();

                    if(!isset(WorkSession::get()->resources[__CLASS__][$key][$pos]))
                        WorkSession::get()->resources[__CLASS__][$key][$pos] = 0;

                    WorkSession::get()->resources[__CLASS__][$key][$pos]+= $count;
                }
            }
    }

    protected static function incrementCounter($key, $pos, $value) {
        
        if($pos < 0 ) {
            Logger()->error("pos $pos < 0", __CLASS__);
            return;
        }
            
        
        $tries = 0;
        
        start:
        $oldval_bin = self::storageStatArchive()->getRange($key, $pos*4,($pos+1)*4);
        if($oldval_bin === false) {
            Logger()->error("self::storageStatArchive()->getRange($key, $pos*4,($pos+1)*4) returns false", __CLASS__);
            usleep(10000);
            $tries++;
            if($tries < 3)
                goto start;
        }
        if(!$oldval_bin||strlen($oldval_bin)<4)
            $oldval_bin = "\0\0\0\0";
        $v = current(unpack('l', $oldval_bin));
        $v+=$value;
        $result = self::storageStatArchive()->setRange($key, $pos*4, pack('l',$v));
        if($result == false) {
            Logger()->error("self::storageStatArchive()->setRange($key, $pos*4, pack('l',$v)) returns false", __CLASS__);
            usleep(10000);
            goto start;
        }
    }

    protected static function key($section, array $object, $event, array $group, $quantization) {
        return $section.">\t".implode("\t\t",$object)."\t>".$event.">\t".implode("\t\t",$group)."\t".$quantization;
    }

    protected static function parseKey($key) {
        
        list($section, $object, $event, $group_and_quantization) = explode(">", $key);
        
        $object = explode("\t\t", substr($object, 1, -1));
        $delimiter_pos = strrpos("\t", $group_and_quantization);
        $quantization = trim(substr($group_and_quantization, $delimiter_pos + 1));
        $group = substr($group_and_quantization, 0, $delimiter_pos);
        $group = explode("\t\t", substr($group, 1, -1));
        
        return array($section, $object, $event, $group, $quantization);
    }

    static function getPosByTime($time, $params) {
        return (int)floor(($time - strtotime($params['start_date']))/($params['seconds']));
    }

    static function deleteString($section, $object, $event='') {
        if ($event)
            return self::storageStatArchive()->deleteKeysByPattern($section.">\t".implode("\t\t",$object)."\t>".$event.">*");
        else
            return self::storageStatArchive()->deleteKeysByPattern($section.">\t".implode("\t\t",$object)."\t>*");
    }

    static function deletePath($section, $object) {
        self::deleteString($section, $object);
    }

    static function deleteEvent($section, $object, $event) {
        self::deleteString($section, $object, $event);
    }
    
    static function handleQueue() {
        static $last_time;
        
        $stat = array();
        
        $elements_count = 0;
        
        if(self::storageQueue()->length() < 1000 && time() - $last_time < 15) {
            sleep(5);
            return 0;
        }

        $last_time = time();
        
        $elements = self::storageQueue()->select(0,100000);
        
        foreach($elements as $element) {
            $element = json_decode($element);

            foreach($element as $key=>$data) {
                foreach($data as $pos=>$count) {
                    if(!isset($stat[$key]))
                        $stat[$key] = array();

                    if(!isset($stat[$key][$pos]))
                        $stat[$key][$pos] = 0;

                    $stat[$key][$pos]+=$count;
                }
            }
            $elements_count++;
        }

        foreach($stat as $key=>$data) {
            foreach($data as $pos=>$count) {
                try {
                    self::incrementCounter($key, $pos, $count);
                } catch (Exception $e) {
                    Logger()->error($e, __CLASS__);
                }
            }
        }
        
        self::storageQueue()->trim(sizeof($elements), -1);
        
        Logger()->info($elements_count, __CLASS__);
        return $elements_count;
    }

}
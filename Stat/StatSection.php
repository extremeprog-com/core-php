<?php

class StatSection {

    function __construct($section) {
        $this->section = $section;
    }

    public function getStat($quantization, $start_date, $end_date, $relations = array(), $filter_groups = array(), $exclude_groups = array()) {

        $Redis = Stat::storageStatArchive();

        $keys = array();

        foreach($Redis->keys($this->section.">*".$quantization) as $key) {
            list(, $object, $event,  $groups) = explode(">", $key);
            foreach($filter_groups as $filter_group)
                if(strpos($groups,"\t$filter_group\t")===false)
                    continue 2;
            foreach($exclude_groups as $filter_group)
                if(strpos($groups,"\t$filter_group\t")!==false)
                    continue 2;
            $keys[] = $key;
        }

        if ($quantization == Stat::QUANTIZATION_DAILY)
            $start_date = strtotime(date('Y-m-d', $start_date).' 05:00:00');

        $start_pos = Stat::getPosByTime($start_date, Stat::$quantization[$quantization]);
        $end_pos =  Stat::getPosByTime($end_date, Stat::$quantization[$quantization]);

        $count = $end_pos - $start_pos;

        $kstat = array();

        foreach($keys as $key) {
            list(, $object, $event) = explode(">", $key);
            $values = $Redis->getRange($key, $start_pos*4, ($end_pos+1)*4-1);
            $current = &$kstat[$object];
            for($i=0; $i < $count; $i++) {
                if(!isset($current)) {
                    $current = [];
                }
                if(!isset($current[$i])) {
                    $current[$i] = [];
                }
                if(!isset($current[$i][$event])) {
                    $current[$i][$event] = 0;
                }
                $current[$i][$event]+=current(unpack('l', substr($values, $i*4,4)?:"\0\0\0\0"));
            }
        }

        ksort($kstat);

        $stat = array();
        foreach($kstat as $object=>$time_stat) {
            $stat[] = array(explode("\t\t",substr($object,1,-1)),$time_stat);
        }

        // add relations
        foreach($stat as &$stat_string){
            foreach($stat_string as &$events) {
                foreach($relations as $key=>$relation) {
                    if(isset($events[$relation[0]])&&@$events[$relation[1]])
                        $events[$key] = $relation[0]/$relation[1];
                }
            }
        }
        return $stat;
    }

    public function rightGetStat($quantization, $start_date, $end_date, $relations = array(), $filter_groups = array(), $exclude_groups = array()) {

        $Redis = Stat::storageStatArchive();

        $keys = array();

        foreach($Redis->keys($this->section.">*".$quantization) as $key) {
            list(, $object, $event,  $groups) = explode(">", $key);
            foreach($filter_groups as $filter_group)
                if(strpos($groups,"\t$filter_group\t")===false)
                    continue 2;
            foreach($exclude_groups as $filter_group)
                if(strpos($groups,"\t$filter_group\t")!==false)
                    continue 2;
            $keys[] = $key;
        }

        if ($quantization == Stat::QUANTIZATION_DAILY)
            $start_date = strtotime(date('Y-m-d', $start_date).' 05:00:00');

        $start_pos = Stat::getPosByTime($start_date, Stat::$quantization[$quantization]);
        $end_pos =  Stat::getPosByTime($end_date, Stat::$quantization[$quantization]);

        $kstat = array();

        foreach($keys as $key) {
            list(, $object, $event) = explode(">", $key);
            $values = $Redis->getRange($key, $start_pos*4, ($end_pos+1)*4-1);
            $current_obj = &$kstat[$object];
            $current_event = &$current_obj[$event];
            for($i=0; strlen($values)>$i*4; $i++) {
                @$current_event[$i]+=current(unpack('l', substr($values, $i*4,4)?:"\0\0\0\0"));
            }
        }

        ksort($kstat);

        $stat = array();
        foreach($kstat as $object=>$time_stat) {
            $stat[] = array(explode("\t\t",substr($object,1,-1)),$time_stat);
        }

        return $stat;
    }

}

<?php

class Supervisor {

    function _start() {
        ob_start([$this, '_echo'], 1);
    }

    function log($msg){
        if(in_context(Supervisor::class)) {

        } else {
            Log::info($msg);
        }
    }

    function _echo($buffer) {

        return "";
    }

    function registerQueue($queue_name) {

    }

    function getFromQueue($queue_name, $elements = 1) {
        if(func_num_args() == 0) {

        }
    }

    function startProcess($cb) {

    }

    function test() { switch(supervisor()->get) {case "a": goto a; }
        $y = 4;

        supervisor()->addToQueue("items", [1,2,3,4,5,6,7,8,9,10]);

        supervisor()->allow_multiprocess(10);

        supervisor()->save_point(); /* start init thread by supervisor */ supervisor()->saveVars(supervisor()->getHash(), get_defined_vars()); return; a: extract(superVisor()->getSavedVars()); /* end init thread by supervisor */

        supervisor()->restart_on_change_hosts_availability(['127.0.0.1', '127.0.0.1:2344', '127.0.0.1:3242', 'host:RedisZMQPort']);
        supervisor()->restart_after(10, 'mins');

        supervisor()->allow_workers(10);

        supervisor()->restart_on_file_change();

        supervisor()->save_point(); /* start init thread by supervisor */ supervisor()->saveVars(supervisor()->getHash(), get_defined_vars()); return; b: extract(superVisor()->getSavedVars()); /* end init thread by supervisor */

        while(true) {
            supervisor()->start_critical_job();
            foreach(self::ZMQPull(20) as $msg) {

            }
            supervisor()->stop_critical_job();
        }

        supervisor()->handleQueue("items", 20, function($items) use ($x, $y){
            supervisor()->addToQueue("master", sizeof($items));
        });

        supervisor()->handleQueue("master", 40, function($items) use($z, $t) {
            supervisor()->var("sum")->add($sum);
        });


//        super()->total_memory();
//        super()->total_cpuload();

//        cluster()->free_mem();
//        cluster()->free_cpu();
//        cluster()->free_diskio();
//        cluster()->free_ssdio();
//        cluster()->total_mem();
//        cluster()->total_cpu();
//        cluster()->total_diskio();
//        cluster()->total_ssdio();

    }

}

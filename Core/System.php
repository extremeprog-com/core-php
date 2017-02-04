<?php

class System {

    static public function InitFiles() {
        echo "==== ".__METHOD__." ====\n";
        $Context = Context();
        $Event = new \_OS\Core\System_InitFiles();
        $listeners = array_unique(\_OS\CoreEvents::getListenersFor(Contexts(), $Event));
        \_OS\CoreEvents::runInEventContext($Event, function() use($listeners) {
            foreach($listeners as $listener) {
                echo $listener."\n";
                call_user_func($listener);
            }
        });
    }

    static public function InitConfigs() {
        echo "==== ".__METHOD__." ====\n";
        $Context = Context();
        $Event = new \_OS\Core\System_InitConfigs();
        $listeners = array_unique(\_OS\CoreEvents::getListenersFor(Contexts(), $Event));
        \_OS\CoreEvents::runInEventContext($Event, function() use($listeners) {
            foreach($listeners as $listener) {
                echo $listener."\n";
                call_user_func($listener);
            }
        });
    }

    static public function InitData() {
        echo "==== ".__METHOD__." ====\n";
        $Context = Context();
        $Event = new \_OS\Core\System_InitData();
        $listeners = array_unique(\_OS\CoreEvents::getListenersFor(Contexts(), $Event));
        \_OS\CoreEvents::runInEventContext($Event, function() use($listeners) {
            foreach($listeners as $listener) {
                echo $listener."\n";
                call_user_func($listener);
            }
        });
    }

    static public function InitDaemons() {
        echo "==== ".__METHOD__." ====\n";
        $Context = Context();
        $Event = new \_OS\Core\System_InitDaemons();
        $listeners = array_unique(\_OS\CoreEvents::getListenersFor(Contexts(), $Event));
        \_OS\CoreEvents::runInEventContext($Event, function() use($listeners) {
            foreach($listeners as $listener) {
                echo $listener."\n";
                call_user_func($listener);
            }
        });
    }

    static public function SelfTest() {
        $reasons = array();
        $fails = array('reasons' => &$reasons);
        Events::System_SelfTest($fails);
        if(!$reasons) {
            echo 'ok';
        }
    }

}

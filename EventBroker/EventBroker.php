<?php

class EventBroker {

    use RedisStorage;
    use ZeroMQPushPull;

    const ZMQ_DAEMON_BIND_ON_PULL = 1;
    const ZMQ_DAEMON_MAX_ONE_TIME_MESSAGES = 1000;
    const ZMQ_DAEMON_RESTART_INTERVAL = 60;
    const ZMQ_DAEMON_USLEEP_AFTER_EMPTY_READ = 200000;

    /**
    * Push triggered event via Message Queue
    *
    * @param Object $event
    * @return void
    */
    static function sendToBroker($event) {
        self::ZMQPush(self::serialize($event));
    }

    /**
    * @return void
    */
    static function daemonizeWatcher() {
        CatchEvent(['EventBrokerHost' => System_InitConfigs ]);
        Taskman::installDaemonUnderTaskman(__CLASS__."-watchQueue", "php-r 'EventBroker::watchQueue();'");
    }

    static function ZMQPullMessageHandler($event_strings) {
        $method2events = [];

        foreach($event_strings as $event_string) {
            $event = self::unserialize($event_string);
            foreach(self::getAllListeners($event) as $method) {
                if(!isset($method2events[$method])) {
                    $method2events[$method] = [];
                }
                $method2events[$method][] = $event_string;
            }
        }
        if(sizeof($event_strings)) {
            self::RedisStorage()->multi();
            foreach ($method2events as $method => $events) {
                foreach($events as $event) {
                    self::RedisStorage()->lPush($method, $event);
                }
            }
            self::RedisStorage()->exec();
        }
    }

    /**
    * @param Object $object
    * @return string
    */
    static function serialize($object) {
        return serialize($object);
    }

    /**
    * @param string $string
    * @return Object
    */
    static function unserialize($string) {
        return unserialize($string);
    }

    /**
    * @param Object $object
    * @return array
    */
    static function getAllListeners($object) {
        return array_keys(\_OS\CoreEvents::$eventsMap[get_class($object)]['queue']);
    }

    /**
    * @static
    * @param string $method
    * @return void
    */
    static function handleQueueForMethod($method) {
        for ($t0 = microtime(true); microtime(true) < $t0 + 10;) {
            $response = self::RedisStorage()->blPop($method, 10);
            if($response) {
                $data = $response[1];
                if($data) {
                    \_OS\CoreEvents::$LastCatchedEvents = array( self::unserialize($data) );
                    call_user_func($method);
                } else {
                    Taskman::deleteDaemonUnderTaskman($method);
                }
            }
        }
    }

    /**
    * @return void
    */
    static function watchQueue() {
        $t0 = microtime(true);
        while(microtime(true) < $t0 + 60) {
            $queues = self::RedisStorage()->keys("*");
            // start new handlers
            foreach($queues as $method) {
                Taskman::installDaemonUnderTaskman($method, 'php-r \'EventBroker::handleQueueForMethod("' . $method . '");\'');
            }
            // stop old handlers
            foreach(Taskman::getRunningTasksByPattern('EventBroker::handleQueueForMethod') as $method) {
                if(!in_array($method, $queues)) {
                    Taskman::deleteDaemonUnderTaskman($method);
                }
            }
            sleep(5);
        }
    }
}

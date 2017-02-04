<?php

trait ZeroMQPushPull {

    static $_ZMQPushConnection;
    static $_ZMQPullConnection;

    static function ZMQPush($msg) {
        static $time;
        if(!self::$_ZMQPushConnection) { //microtime(true) > $time + 1 ) {
            self::_ZMQPushMakeConnection();
            $time = microtime(true);
        }
        self::$_ZMQPushConnection->send($msg);
    }

    static function ZMQPull() {
        $conn = self::$_ZMQPullConnection ?: self::_ZMQPullMakeConnection();
        $msg = $conn->recv(ZMQ::MODE_NOBLOCK);
        return $msg;
    }

    static function _ZMQPushMakeConnection() {

        if(!self::ZMQ_DAEMON_BIND_ON_PULL) {
            throw new Exception('Bind on push is not realized yet');
        }

        if(method_exists(__CLASS__, 'ZMQGetDsn')) {
            $dsn = self::ZMQGetDsn();
        } else {
            $dsn = 'ipc://'. self::_getPathPrefix() . "." . self::_ZMQPushPullGetSid(). ".sock";
        }

        $Context = new ZMQContext(1, true);
        self::$_ZMQPushConnection = new ZMQSocket($Context, ZMQ::SOCKET_PUSH, $dsn.'-push');
        if(defined('ZMQ::SOCKOPT_SNDHWM')) {
            self::$_ZMQPushConnection->setSockOpt(ZMQ::SOCKOPT_SNDHWM, 0);
        }
        self::$_ZMQPushConnection->setSockOpt(ZMQ::SOCKOPT_LINGER, 3000);
        self::$_ZMQPushConnection->connect($dsn);
        return self::$_ZMQPushConnection;
    }

    static function _ZMQPullMakeConnection() {

        if(!self::ZMQ_DAEMON_BIND_ON_PULL) {
            throw new Exception('Connect on pull is not realized yet');
        }

        if(method_exists(__CLASS__, 'ZMQGetDsn')) {
            $dsn = self::ZMQGetDsn();
        } else {
            $dsn = 'ipc://'. self::_getPathPrefix() . "." . self::_ZMQPushPullGetSid(). ".sock";
        }

        $Context = new ZMQContext(1, true);
        self::$_ZMQPullConnection = new ZMQSocket($Context, ZMQ::SOCKET_PULL, $dsn.'-pull');
        if(defined('ZMQ::SOCKOPT_RCVHWM')) {
            self::$_ZMQPullConnection->setSockOpt(ZMQ::SOCKOPT_RCVHWM, 0);
        }
        self::$_ZMQPullConnection->bind($dsn);
        return self::$_ZMQPullConnection;
    }

    static function _ZMQPushRenewConnections() {
        CatchEvent(WorkSession_Start::class);
        self::$_ZMQPushConnection = null;
    }

    static function _getPathPrefix() {
        return PATH_ENV.'/var/'.strtr(__CLASS__, ['\\' => ":" ]);
    }

    static function _ZMQPushPullGetSid() {
        $sidfile = self::_getPathPrefix() . '.sid';
        if (!file_exists($sidfile)) {
            file_put_contents($sidfile, '100');
        }
        $sid = (int)file_get_contents($sidfile);
        return $sid;
    }

    static function _ZMQPullStopHandling() {
        if(method_exists(__CLASS__, 'ZMQGetDsn')) {
            self::$_ZMQPullConnection->unbind(self::ZMQGetDsn());
        } else {
            $sidfile = self::_getPathPrefix() . '.sid';
            file_put_contents($sidfile, (self::_ZMQPushPullGetSid() + 1)%100 + 100);
        }
    }

    static function _ZMQPushPullDaemonizeBindHandler() {
        CatchEvent(System_InitConfigs);
        if(defined(__CLASS__."::ZMQ_DAEMON_OFF")) {
            Taskman::deleteDaemonUnderTaskman(__CLASS__."::_ZMQPushPullDaemon");
        } else {
            Taskman::installDaemonUnderTaskman(__CLASS__."::_ZMQPushPullDaemon", "php-r '".__CLASS__."::_ZMQPushPullDaemon();'");
        }
    }

    /**
     * @return void
     */
    static function _ZMQPushPullDaemon() {
        $t0 = microtime(true);

        // work normally
        while(microtime(true) < $t0 + self::ZMQ_DAEMON_RESTART_INTERVAL) {
            $msgs = [];
            while(sizeof($msgs) < self::ZMQ_DAEMON_MAX_ONE_TIME_MESSAGES) {
                $msg = self::ZMQPull();
                if(!$msg){
                    break;
                }
                $msgs[] = $msg;
            }

            if(sizeof($msgs)) {
                self::ZMQPullMessageHandler($msgs);
            } else {
                usleep(self::ZMQ_DAEMON_USLEEP_AFTER_EMPTY_READ);
            }
        }

        // stop current pull socket and handle waiting messages
        self::_ZMQPullStopHandling();

        do {
            $msgs = [];
            while(sizeof($msgs) < self::ZMQ_DAEMON_MAX_ONE_TIME_MESSAGES) {
                $msg = self::ZMQPull();
                if(!$msg){
                    break;
                }
                $msgs[] = $msg;
            }

            if(sizeof($msgs)) {
                self::ZMQPullMessageHandler($msgs);
            }

        } while(sizeof($msgs));

    }

}

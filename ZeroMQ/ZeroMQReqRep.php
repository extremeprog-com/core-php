<?php

trait ZeroMQReqRep {

    static $_ZMQReqConnection;
    static $_ZMQRepConnection;

    static function ZMQReq($msg) {
        $conn = self::$_ZMQReqConnection ?: self::_ZMQReqMakeConnection();
        $conn->send($msg);
    }

    static function ZMQRep() {
        $conn = self::$_ZMQRepConnection ?: self::_ZMQRepMakeConnection();
        $msg = $conn->recv(ZMQ::MODE_NOBLOCK);
        return $msg;
    }

    static function _ZMQReqMakeConnection() {

        if(!self::ZMQ_DAEMON_BIND_ON_REP) {
            throw new Exception('Bind on Req is not realized yet');
        }

        if(method_exists(__CLASS__, 'ZMQGetDsn')) {
            $dsn = self::ZMQGetDsn();
        } else {
            $dsn = 'ipc://'. self::_getPathPrefix() . "." . self::_ZMQReqRepGetSid(). ".sock";
        }

        $Context = new ZMQContext(1, true);
        self::$_ZMQReqConnection = new ZMQSocket($Context, ZMQ::SOCKET_Req, $dsn.'-Req');
        if(defined('ZMQ::SOCKOPT_SNDHWM')) {
            self::$_ZMQReqConnection->setSockOpt(ZMQ::SOCKOPT_SNDHWM, 0);
        }
        self::$_ZMQReqConnection->connect($dsn);
        return self::$_ZMQReqConnection;
    }

    static function _ZMQRepMakeConnection() {

        if(!self::ZMQ_DAEMON_BIND_ON_REP) {
            throw new Exception('Connect on Rep is not realized yet');
        }

        if(method_exists(__CLASS__, 'ZMQGetDsn')) {
            $dsn = self::ZMQGetDsn();
        } else {
            $dsn = 'ipc://'. self::_getPathPrefix() . "." . self::_ZMQReqRepGetSid(). ".sock";
        }

        $Context = new ZMQContext(1, true);
        self::$_ZMQRepConnection = new ZMQSocket($Context, ZMQ::SOCKET_Rep, $dsn.'-Rep');
        if(defined('ZMQ::SOCKOPT_RCVHWM')) {
            self::$_ZMQRepConnection->setSockOpt(ZMQ::SOCKOPT_RCVHWM, 0);
        }
        self::$_ZMQRepConnection->bind($dsn);
        return self::$_ZMQRepConnection;
    }

    static function _getPathPrefix() {
        return PATH_ENV.'/var/'.strtr(__CLASS__, ['\\' => ":" ]);
    }

    static function _ZMQRepStopHandling() {
        if(method_exists(__CLASS__, 'ZMQGetDsn')) {
            self::$_ZMQRepConnection->unbind(self::ZMQGetDsn());
        } else {
            $sidfile = self::_getPathPrefix() . '.sid';
            file_put_contents($sidfile, (self::_ZMQReqRepGetSid() + 1)%100 + 100);
        }
    }

    static function _ZMQReqRepDaemonizeBindHandler() {
        Taskman::installDaemonUnderTaskman(__CLASS__."::_ZMQRepDaemon", "php-r '".__CLASS__."::_ZMQRepDaemon();'");
    }

    /**
     * @return void
     */
    static function _ZMQReqRepDaemon() {
        $t0 = microtime(true);
        // under construction


    }
} 
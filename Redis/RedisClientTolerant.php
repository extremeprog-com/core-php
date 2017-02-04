<?php

class RedisClientTolerant {
    
    /** @var Redis */
    protected $Redis;
    
    protected $connected = false;
    
    protected $connection_params = array();
    
    const max_connect_tries = 7;
    const connect_timeout = 0.01;
    const usleep_before_reconnect = 50000;
    
//    protected $commands = array();
    
    function __construct($host_or_sock, $port = 6379, $db = 0, $timeout = self::connect_timeout) {
        $this->Redis = new Redis();
        $this->connection_params = array($host_or_sock, $port, $timeout, $db);
    }

    protected $instance;

    function setInstance($instance) {
        $this->instance = $instance;
    }

    function ping() {
//        $this->commands[] = 'ping';
        
        $b_ping = BENCHMARK();
        if ($b_ping) {Benchmark::start(self::benchmarkName, $bench=('PING '.json_encode($this->connection_params)));}
        try{
            if(!$this->Redis->info())
                throw new Exception('Warning! Info failed, ping will return false.');
            $pong = $this->Redis->ping();
            if ($b_ping) {Benchmark::end(self::benchmarkName, $bench);}
        } catch(Exception $e) {
            if ($b_ping) {Benchmark::endTimeMsg(array(self::benchmarkName, $bench), '--- exception ---');}
            Logger()->warn("ping exception: ".$e->getMessage());
            $pong = false;
        }
        
        return $pong;
    }
    
    const benchmarkName = "REDIS";
    
    function __call($name,$args) {

        $next_state = !$this->connected?'connect':'request';
        $connect_tries = 0;

        $b = BENCHMARK();
        
        while(true) {
            switch($next_state) {
                case 'connect':
                    $connect_tries++;
                    
                    if ($b) {Benchmark::start(self::benchmarkName, $bench=('connect '.json_encode($this->connection_params)));}
                    try{
//                        $this->commands = array();
                        $connection_result = call_user_func_array(array($this->Redis, 'connect'),$this->connection_params);
                        if($this->connection_params[3])
                            if(!$this->Redis->select((int)$this->connection_params[3]))
                                throw new Exception('cannot select db');
//                        $this->connected = true;
                    } catch(Exception $e) {
                        $connection_result = false;
                    }
                    if ($b) {Benchmark::end(self::benchmarkName, $bench);}
                            
                    if($connection_result == false)
                        $next_state = 'check_reconnect_tries';
                    else
                        $next_state = 'request'; 

                break;
                case 'request':
                    if ($b) {Benchmark::start(self::benchmarkName, $bench=($name.' '.json_encode($this->connection_params)));}

                    try{
//                        $this->commands[] = $name;
                        $result = dbg::$i->sih = call_user_func_array(array($this->Redis,$name), $args);
                    } catch(Exception $e) {
                        Logger()->error($e, __CLASS__);
                        $result = false;
                    }

                    if($result==='+PONG'){
                        // хак блин
                        Logger()->warn("+PONG response on ".json_encode(array($name,$args)),'MessageBus');
                        $result = false;
                        $next_state = 'exit';
                    }
                    elseif($result==false){
                        if(!$this->ping()){
                            $next_state = 'check_reconnect_tries';
                            if ($b) {Benchmark::endTimeMsg(array(self::benchmarkName, $bench), '---lost connection---');}
                        } else {
                            $next_state = 'exit';
                            if ($b) {Benchmark::endTimeMsg(array(self::benchmarkName, $bench), ' no data: ');}
                        }
                    }
                    else {
                        $next_state = 'exit';
                        try{
                            $size = strlen(json_encode($result));
                        } catch(Exception $e) {
                            $size = 0;
                        }
                        if ($b) {Benchmark::endTimeMsg(array(self::benchmarkName, $bench), 'size: '.$size);}
                    }
                break;
                case 'check_reconnect_tries':
                    
                    if(isset($result)) {
                        $log_message = 'lost connection while fetching result of '.$name.json_encode($args);
                        unset($result);
                    } else {
                        $log_message = 'unable to connect';
                    }
                    
                    if($connect_tries < self::max_connect_tries) {
                        Logger()->warn(__CLASS__.': '.$log_message.', server '.json_encode($this->connection_params).', trying to reconnect '.$connect_tries.'/'.self::max_connect_tries);
                        try{
                            $this->Redis->close();
                        } catch(Exception $e) {
                            Logger()->error(json_encode($e), __CLASS__);
                        }
                        usleep(self::usleep_before_reconnect * $connect_tries);
                        $next_state = 'connect';
                    } else {
                        Logger()->warn($message = __CLASS__.': '.$log_message.', server '.json_encode($this->connection_params).', max '.self::max_connect_tries.' tries exceeded');
                        $this->connected = false;
                        if(isset($solve_connection_problem)) {
                            throw new Exception($message);
                        }
                        $next_state = 'solve_connection_problem';
                    }
                break;
                case 'solve_connection_problem':
                    throw new Exception("Connection problem for instance {$this->instance}");
//                    if(!isset($solve_connection_problem)) {
//                        if(Requests::RedisInstance_SolveConnectionFail($this->instance)) {
//                            $connect_tries = 0;
//                            $next_state = 'connect';
//                            $solve_connection_problem = true;
//                        } else {
//                            throw new Exception("Cannot connect or solve connection problem for instance {$this->instance}");
//                        }
//                    } else {
//                        throw new Exception("Connection problem not solved for instance {$this->instance}");
//                    }
                break;
                case 'exit':
                    if(isset($result))
                        return $result;
                    else
                        return false;
                break;
            }
        }
        
    }
    
}

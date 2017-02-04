<?php
trait NginxStateDaemon {
    use MasterSlaveStateDaemon;
    public static $roles = [
        'master' => 'NginxBalancer',
        'slave'  => 'NginxSlave',
        'pool'   => 'NginxApplication',
    ];
    
    public static $states = [
        'init',
        'ready',
        'make_slave',
        'check_slave',
        'check_master',
        'master_down',
        'make_master',
    ];

    /**
     * Первичная инициализация 
     */
    public static function init() {
        /** @var $Request NginxStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['state' => 'init']]); 
        echo __METHOD__;
        $event_class = static::class . '_Reconfigure';
        FireEvent(new $event_class);

        self::setState($Request->role, ['state' => 'ready']);
        return true;
    }
    
    /**
     * Создание слейва после поднятия мастера
     */
    public static function makeSlave() {
        /** @var $Request NginxStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['role' => 'master', 'state' => 'ready']]); 
        echo __METHOD__;
        
        // Ничего не делаем, если хост мультироль, слейв не запускаем
        $config = Project::getConfig();
        if (isset($config['multirole']) && $config['multirole']) {
            return true;
        }
        
        // Ищем подходящий хост для развертывания
        $hosts = HostRole::getRoleHosts(static::getHostRole('pool'));
        while (in_array($host = array_shift($hosts), [HostRole::getRoleHosts(static::getHostRole('master'), true, true), HostRole::getRoleHosts(static::getHostRole('slave'), true, true)])) {}
        if ($host) {
            $slave_role  = static::getHostRole('slave');
            $pool_role   = static::getHostRole('pool');
            
            $slave_host = HostRole::getRoleHosts($slave_role, true, true);
            
            $slave_role::addToHost($host);
            
            // Если у слейва был хост, а не определяем новый
            if ($slave_host) {
                $slave_role::removeFromHost($slave_host);
                
                // Rotate
                $pool_role::removeFromHost($slave_host);
                $pool_role::addToHost($slave_host);
            }    
            self::setState($Request->role, ['state' => 'make_slave']);
            self::setState('slave', ['state' => 'init']);
        }        
        
        self::setState($Request->role, ['state' => 'make_slave']);
        return true;
    }
    
    /**
     * Ожидание пока сервер со слейвом поднимется
     */
    public static function waitSlaveInit() {
        /** @var $Request NginxStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['role' => 'master', 'state' => 'make_slave']]); 
        echo __METHOD__;
        
        // Если удается пингануть, значит поднят
        if (static::checkNginxHost(HostRole::getRoleHosts(static::getHostRole('slave')), true, true)) {
            self::setState($Request->role, ['state' => 'check_slave']);
        }
        return true;
    }
    
    /**
     * Проверка доступности мастером слейва
     */
    public static function checkSlave() {
        /** @var $Request NginxStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['role' => 'master', 'state' => 'check_slave']]); 
        echo __METHOD__;
  
        if (!static::checkNginxHost(HostRole::getRoleHosts(static::getHostRole('slave')), true, true)) {
            self::setState('master', ['state' => 'ready']);
            self::setState('slave', ['state' => 'init']);
        }
        return true;
    }
    
    /**
     * Проверка доступности мастера слейвом
     */
    public static function checkMaster() {
        /** @var $Request NginxStateDaemon_GoNextState */
        $Request = CatchRequest([static::class . '_GoNextState' => ['role' => 'slave', 'state' => 'ready']]); 
        echo __METHOD__;
        if (!static::checkNginxHost(HostRole::getRoleHosts(static::getHostRole('master')), true, true)) {
            self::setState('slave', ['state' => 'master_down']);
        }
        return true;
    }
    
    /**
     * При падении мастера слейв становится мастером
     */
    public static function makeSlaveMaster() {
        /** @var $Request NginxStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['role' => 'slave', 'state' => 'master_down']]); 
        echo __METHOD__;
  
        $master_role = static::getHostRole('master');  
        $slave_role = static::getHostRole('slave');
        $pool_role  = static::getHostRole('pool');
        
        $master_host = HostRole::getRoleHosts($master_role, true, true);
        $slave_host  = HostRole::getRoleHosts($slave_role, true, true);
        
        $master_role::addToHost($slave_host);
        $master_role::removeFromHost($master_host);
        $slave_role::removeFromHost($slave_host);
        
        // Rotate
        $pool_role::removeFromHost($master_host);
        $pool_role::addToHost($master_host);
        
        //$slave_ip = gethostname(HostRole::getRoleHosts(static::getHostRole('master'), true, true));
        //$RobotClient = new RobotClient('https://robot-ws.your-server.de', '#ws+cqJF9zYh', 'VX3fjXkFSEGbsHq3');
        //$RobotClient->failoverRoute(Project::getConfig()['failover_ip'], $slave_ip);
  
        self::setState('master', ['state' => 'init']);
        self::setState('slave',  ['state' => 'init']);
        return true;
    }

    /**
     * Проверка доступности балансера
     * @param	string $host
     * @return  bool
     */    
    public static function checkNginxHost($host) {
        $http_host = method_exists(static::class, 'getSiteNames') ? static::getSiteNames()[0] : $host;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, gethostbyname($host.'.ext') . "/_ping");
        curl_setopt($ch, CURLOPT_PORT, 80);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 3000);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Host: {$http_host}",
            "Accept: */*",
            "User-Agent: " . static::class . "NginxChecker",
        ]);
        
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);        
        curl_close($ch);
        
        return $code === 200 && $response === 'OK' ? true : false;
    }
}

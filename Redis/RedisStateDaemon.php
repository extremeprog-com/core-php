<?php
trait RedisStateDaemon {
    use MasterSlaveStateDaemon;
    public static $roles = [
        'master' => 'RedisHost',
        'slave'  => 'RedisSlaveHost',
        'pool'   => 'RedisCandidateHost',
    ];
    
    public static $states = [
        'init',
        'run',
        'ready',
        'make_slave',
        'check_slave',
        'check_master',
        'master_down',
        'make_master',
    ];
    
    public static $backup = true;

    /**
     * Первичная инициализация 
     */
    public static function init() {
        /** @var $Request RedisStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['state' => 'init']]); 
        echo __METHOD__;
        
        static::initConfigs($Request->role);
        static::runServerDaemon($Request->role);
        self::setState($Request->role, ['state' => 'ready']);
        return true;
    }
    
    /**
     * Инициализация конфигурационных файлов
     * @param   string  $role
     */
    public static function initConfigs($role = 'master') {
        $daemon_name = static::getHostRole('master');
        $port = Taskman::getPortFor(static::getHostRole($role));
        $ip   = gethostbyname(HostRole::getRoleHosts(static::getHostRole($role), true, true).'.int');
        
        if ($role === 'slave') {
            $master_host = HostRole::getRoleHosts(static::getHostRole('master'), true, true);
            $master_ip   = gethostbyname($master_host);
            $master_port = Taskman::getPortFor(static::getHostRole('master'), null, $master_host);
        }
        
        $working_dir = PROJECTDATA . '/' . $daemon_name;
        if (!is_dir($working_dir)) {
            mkdir($working_dir, 0755, true);
        }
        
        Taskman::prepareConfigs(__DIR__."/etc/", PROJECTENV."/etc/redis/$daemon_name/", [
            '%PORT%'        => $port,
            '%ROLE%'        => $daemon_name,
            '%WORKING_DIR%' => $working_dir,
            '%SLAVE_CONFIG%'=> $role === 'master' ? '' : 'slaveof ' . $master_ip . ' ' . $master_port,
        ]);        
    }
    
    /**
     * Установка запуска демона в таскман
     * @param	string	$role	
     */
    public static function runServerDaemon($role = 'master') {
        $daemon_name = static::getHostRole('master');
        $port = Taskman::getPortFor(static::getHostRole($role));
        $cmd = 'redis-server '.PROJECTENV."/etc/redis/$daemon_name/redis.conf --port $port";
        Taskman::installDaemonUnderTaskman($daemon_name, $cmd);        
    }
    
    /**
     * Создание слейва после поднятия мастера
     */
    public static function makeSlave() {
        /** @var $Request RedisStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['role' => 'master', 'state' => 'ready']]); 
        echo __METHOD__;
        
        // Ничего не делаем, если хост мультироль, слейв не запускаем
        $config = Project::getConfig();
        if (isset($config['multirole']) && $config['multirole']) {
            return true;
        }
        
        // Ищем подходящий хост для развертывания бэкапа и инициализации слейва
        $hosts = HostRole::getRoleHosts($pool_role = static::getHostRole('pool'));
        if ($host = array_shift($hosts)) {
            // Обнуляем метку времени
            static::redisClient('master', 1)->set('redis_alive_ts', 0);

            $slave_host = HostRole::getRoleHosts($slave_role = static::getHostRole('slave'), true, true);
            
            $slave_role::addToHost($host);
            if ($slave_host) {
                $slave_role::removeFromHost($slave_host);
                $pool_role::addToHost($slave_host);
            }
            $pool_role::removeFromHost($host);
            
            self::setState($Request->role, ['state' => 'make_slave']);
            self::setState('slave', ['state' => 'init']);
        }
                
        
        return true;
    }
    
    /**
     * Ожидание пока сервер со слейвом поднимется
     */
    public static function masterWaitSlaveInit() {
        /** @var $Request RedisStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['role' => 'master', 'state' => 'make_slave']]); 
        echo __METHOD__;
        
        $host = HostRole::getRoleHosts(static::getHostRole('slave'), true, true);
        $port = Taskman::getPortFor(static::getHostRole('slave'), null, $host);
        if (!static::checkSocket($host, $port)) {
            sleep(3);
            return false;
        }
    
        self::setState($Request->role, ['state' => 'check_slave']);
        return true;
    }
    
    public static function slaveWaitSelfInit() {
        /** @var $Request RedisStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['role' => 'slave', 'state' => 'ready']]);
        echo __METHOD__;
        
        $host = HostRole::getRoleHosts(static::getHostRole('slave'), true, true);
        $port = Taskman::getPortFor(static::getHostRole('slave'), null, $host);
        if (!static::checkSocket($host, $port)) {
            sleep(3);
            return false;
        }
        
        $info = static::redisClient('slave')->info();
        if ($info['loading'] == 0 && isset($info['master_link_status']) && !$info['master_link_status'] == 'up') {
            sleep(3);
            return false;
        }
    
    
        self::setState($Request->role, ['state' => 'check_master']);
        return true;        
    }
    
    /**
     * Проверка доступности мастером слейва
     */
    public static function checkSlave() {
        /** @var $Request RedisStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['role' => 'master', 'state' => 'check_slave']]); 
        echo __METHOD__;

        static::redisClient('master', 1)->set('redis_alive_ts', $ts = time());
    
        sleep(3);
        
        // Опрашиваем слейв на предмет установленной метки, если нет
        // коннекта или по каким-то причинам метка не установилась
        // переходим в состояние подъема слейва
        try {
            $slave_ts = static::redisClient('slave', 1)->get('redis_alive_ts');
                        
            if ($slave_ts != $ts) {
                throw new Exception('Slave is down');
            }
        } catch (Exception $E) {
            Log::error([
                'message' => $E->getMessage(),
            ], static::class);
            self::setState($Request->role, ['state' => 'init']);
        }
        
        static::makeDailyBackup();  
        return true;
    }
    
    /**
     * Проверка доступности мастера слейвом
     */
    public static function checkMaster() {
        /** @var $Request RedisStateDaemon_GoNextState */
        $Request = CatchRequest([static::class . '_GoNextState' => ['role' => 'slave', 'state' => 'check_master']]); 
        echo __METHOD__;
        
        sleep(3); // A little bit cheat
        
        // Если мастер не пишет, переходим на стейт создания слейва мастером
        // Если слейв не отвечает, исключаем текущий хост из обихода
        // и ждем, когда мастер поднимет другой слейв
        // Query slave host
        try {
            $ts = static::redisClient('slave', 1)->get('redis_alive_ts');
            
            if ($ts && $ts < time() - 20) {
                self::setState($Request->role, ['state' => 'master_down']);
            }            
        } catch (Exception $E) {
            static::stopProcessing();
        }
        
        return true;
    }
    
    /**
     * При падении мастера слейв становится мастером
     */
    public static function makeSlaveMaster() {
        /** @var $Request RedisStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['role' => 'slave', 'state' => 'master_down']]); 
        echo __METHOD__;

        // Изменяем роли
        $host = Taskman::getHostname();
        $master_host = HostRole::getRoleHosts($master_role = static::getHostRole('master'), true, true);
        $slave_role = static::getHostRole('slave');
        $pool_role = static::getHostRole('pool');
        
        Log::error([
            'message'           => 'Make slave master',
            'old_master_host'   => $master_host,
        ], static::class);
        
        echo "\nadd master $host\nremove master $master_host\nremove slave $host";
        
        // Помечаем мастером
        static::redisClient($Request->role)->slaveOf();
        
        $master_role::addToHost($host);
        $master_role::removeFromHost($master_host);
        $slave_role::removeFromHost($host);
        $pool_role::addToHost($master_host);
        
        // Удаляем слейв с хоста
        Taskman::deleteDaemonUnderTaskman(static::getHostRole('slave'));        
        
        // Меняем пути до папок в хранилище состояния
        $slave = self::getState('slave');
        self::setState('slave', [
            'state'  => 'init',
            'dir'    => '',
            'config' => '',
        ]);
        
        self::setState('master', [
            'state'  => 'init',
            'dir'    => '',
            'config' => '',
        ]);
        
        // Extra wait for becoming slave master
        sleep(10);
        
        return true;
    }
    
    /**
     * Получение коннекта на редис
     * @param	string $role
     * @param int $db
     * @return	RedisClient
     */
    public static function redisClient($role = 'master', $db = 0) {
        return new RedisClient([
            'host' => $host = HostRole::getRoleHosts($host_role = static::getHostRole($role), true, true),
            'port' => Taskman::getPortFor($host_role, null, $host),
            'db'   => $db,
        ]);        
    }
    

    /**
     * Оставнока демона на хосте (убрана роль, а демон в таскмане)
     * @return void
     */    
    public static function removeDaemonFromHost() {
        CatchEvent(static::class . '_Stopped');
        Taskman::deleteDaemonUnderTaskman(static::getHostRole('master'));
        Taskman::deleteDaemonUnderTaskman(static::getHostRole('slave'));
    }
}

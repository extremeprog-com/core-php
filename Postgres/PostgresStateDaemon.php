<?php

/**
 * Master states: init -> dir_ready -> base_ready -> make_slave -> slave_init -> check_slave
 * Slave states: init -> copy_backup -> slave_ready -> check_master -> make_master -> (master base_ready)
 */
class PostgresStateDaemon {
    use MasterSlaveStateDaemon;
    protected static $pgdir = '/usr/lib/postgresql/9.3';
    
    public static $roles = [
        'master' => 'Host',
        'slave'  => 'SlaveHost',
        'pool'   => 'CandidateHost',
    ];

    protected static $states = [
        'init',
        'dir_ready',
        'base_ready',
        'make_slave',
        'slave_init',
        'check_slave',
        'copy_backup',
        'slave_ready',
        'check_master',
        'make_master',
    ];
    
    public static $backup = true;    

    /**
     * Инициализация папки под базу, формирование конфигов
     * @return true
     */
    public static function initDir() {
        /** @var $Request PostgresStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['state' => 'init', 'role' => 'master']]);
        
        $instance = static::getNextInstance();
        $confdir = PROJECTENV . '/etc/postgres' . $instance;

        if(!file_exists($confdir)) {
            mkdir($confdir, 0755, true);
        }

        $dir = PROJECTDATA . '/postgres' . $instance;
        if(!file_exists($dir)) {
            mkdir($dir, 0700, true);
        }

        $port = Taskman::getPortFor(static::getHostRole('master'));

        Taskman::prepareConfigs(__DIR__ . '/etc/', $confdir, [
            '%DATA_DIR%'        => $dir,
            '%CONFIG_DIR%'      => $confdir,
            '%PORT%'            => $port,
            '%SHARED_BUFFERS%'  => strpos(PROJECT, 'prod') ? '2G'    : '24MB',
            '%WORK_MEM%'        => strpos(PROJECT, 'prod') ? '512MB' : '1MB',
        ]);
        
        passthru(self::$pgdir . "/bin/initdb $dir");
        `rm $dir/pg_hba.conf $dir/pg_ident.conf $dir/postgresql.conf`;
            
        self::setState($Request->role, [
            'state'  => 'dir_ready',
            'dir'    => $dir,
            'config' => $confdir . '/postgresql.conf',
        ]);
        return true;
    }
    
    /**
     * Инициализация базы, создание юзеров, запуск инстанса
     * @return true
     */ 
    public static function startMasterDatabase() {
        /** @var $Request PostgresStateDaemon_GoNextState */        
        $Request = CatchRequest([static::class . '_GoNextState' => ['state' => 'dir_ready', 'role' => 'master']]);
        echo __METHOD__;
        
        $port = Taskman::getPortFor(static::getHostRole('master'));
        
        // Daemon process
        Taskman::installDaemonUnderTaskman('postgres', self::$pgdir . '/bin/postgres -p ' . $port . ' -c config_file=' . self::getState($Request->role)['config'], true);

        // wait for postgres is running
        while (!self::checksockopen('localhost.ext', $port)) {
            sleep(1);
        }
        
        // Create db, users etc
        passthru(static::$pgdir . '/bin/createuser -h ' . PROJECTENV . '/var/ -p ' . $port . ' -S -D -l -R ' . PROJECT);
        passthru(static::$pgdir . '/bin/createdb -h ' . PROJECTENV . '/var/ -O ' . PROJECT . ' -p ' . $port . ' ' . PROJECT);        
        passthru('echo "ALTER USER ' . PROJECT . ' WITH PASSWORD \'' . md5(DB::secret . PROJECT) . '\';"|' . static::$pgdir . '/bin/psql -h ' . PROJECTENV . '/var/ -p ' . $port . ' --user=' . PROJECTUSER . ' ' . PROJECT);
        
        // Последовательность для контроля доступности слейва
        DB::connect()->query('CREATE SEQUENCE postgres_alive_seq');        
        self::setState($Request->role, ['state' => 'base_ready']);
        return true;
    } 

    static public function checksockopen($host, $port) {
        try {
             if(!$f = fsockopen($host, $port)) {
                 return false;
             }
        } catch(Exception $e) {
            return false;
        }
        fclose($f);
        return true;
    }

    /**
     * Поднятие слейва для мастера,, добавляем роль и инициализируем
     * процесс запуска слейва на добавленном хосте из кандидатов
     * @return true
     */
    public static function makeSlave() {
        /** @var $Request PostgresStateDaemon_GoNextState */           
        $Request = CatchRequest([static::class . '_GoNextState' => ['state' => 'base_ready', 'role' => 'master']]);
        echo __METHOD__;
        
        // Ничего не делаем, если etcd не присутствует, slave не разворачиваем
        $config = Project::getConfig();
        if ( !Etcd::enabled() ) {
            return true;
        }
        
        // Ищем подходящий хост для развертывания бэкапа и инициализации слейва
        $pool_role = static::getHostRole('pool');                
        $hosts = HostRole::getRoleHosts($pool_role);
        if ($host = array_shift($hosts)) {
            
            $Stmt = DB::connect()->prepare("ALTER SEQUENCE postgres_alive_seq RESTART WITH 1");
            $Stmt->execute();
            
            $master_role = static::getHostRole('master');
            $slave_role = static::getHostRole('slave');
            
            $slave_host = HostRole::getRoleHosts($slave_role, true, true);
            
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
     * Ожидание инициализации слейва, чтобы перейти к стадии проверки
     * @return true
     */
    public static function waitSlaveInit() {
        /** @var $Request PostgresStateDaemon_GoNextState */           
        $Request = CatchRequest([static::class . '_GoNextState' => ['state' => 'make_slave', 'role' => 'master']]);
        echo __METHOD__;
        if (self::getState('slave')['state'] !== 'slave_ready') {
            $ts = time();
            while (true) {
                // Если слейв так и не поднялся за отведенное ему время
                if (($ts + 300) < time()) {
                    self::setState($Request->role, ['state' => 'base_ready']);
                    Log::error([
                        'message' => 'Slave could not start. Trying next host...'
                    ], static::class);
                    break;
                }
            }
        }
        
        
        
        // Если слейв успешно поднялся, то изменяем состояние
        // В противном случае просто ожидаем успеха коннекта
        try {
            DB::connect(DB::getDsn(static::getHostRole('slave')));
            
            self::setState($Request->role, ['state' => 'slave_init']);
        } catch (Exception $E) {
            Log::warn([
                'message'    => 'Waiting slave init',
                'db_message' => $E->getMessage(),
            ], __CLASS__);
            
            sleep(5);
        }
        
        return true;
    }
    
    /**
     * Инициализация слейва
     * @return true
     */
    public static function masterCheckSlave() {
        /** @var $Request PostgresStateDaemon_GoNextState */           
        $Request = CatchRequest([static::class . '_GoNextState' => ['state' => 'slave_init', 'role' => 'master']]);
        echo __METHOD__;
        
        $Stmt = DB::connect()->prepare("SELECT setval('postgres_alive_seq', :time)");
        $Stmt->execute(['time' => $ts = time()]);
    
        sleep(5); // Replication lag is up to 3++ sec
        
        // Опрашиваем слейв на предмет установленной метки, если нет
        // коннекта или по каким-то причинам метка не установилась
        // переходим в состояние подъема слейва
        try {
            $slave_ts = DB::connect(DB::getDsn(static::getHostRole('slave')))->query('SELECT last_value FROM postgres_alive_seq', PDO::FETCH_COLUMN, 0)->fetch();
                        
            if ($slave_ts !== $ts) {
                throw new Exception('Slave is down');
            }
        } catch (Exception $E) {
            Log::error([
                'message' => $E->getMessage(),
            ], __CLASS__);
            self::setState($Request->role, ['state' => 'base_ready']);
        }
        
        self::makeDailyBackup();
        return true;
    }     
    
    /**
     * Копирование бэкапа и запуск слейва после запуска
     * @return true
     */
    public static function copyBackupForSlaveInit() {
        /** @var $Request PostgresStateDaemon_GoNextState */           
        $Request = CatchRequest([static::class . '_GoNextState' => ['state' => 'init', 'role' => 'slave']]);
        echo __METHOD__;
        // Запущен ли вообще мастер?
        if (self::getState('master')['state'] !== 'make_slave') {
            echo 'Wait master is up...';
            return false;
        }
        
        // Делаем рсинк актуальных данных с хоста мастерв
        if (!$master_host = HostRole::getRoleHosts(static::getHostRole('master'), true, true)) {
            throw new Exception('Fatal erro! No host for master');
        }
        Rsync::rsyncFrom($master_host, basename(self::getState('master')['dir']));
        
        // Иницализруем папочки
        $instance = static::getNextInstance();
        $confdir = PROJECTENV . '/etc/postgres' . $instance;
        if(!file_exists($confdir)) {
            mkdir($confdir, 0755, true);
        }

        // Get new dir for base and rename copied from master server
        $dir = PROJECTDATA . '/postgres' . $instance;
        rename(self::getState('master')['dir'], $dir);

        $port = Taskman::getPortFor(static::getHostRole('slave'));

        Taskman::prepareConfigs(__DIR__ . '/etc/', $confdir, [
            '%DATA_DIR%'    => $dir,
            '%CONFIG_DIR%'  => $confdir,
            '%PORT%'        => $port,
        ]);
            
        self::setState($Request->role, [
            'dir'    => $dir,
            'config' => $confdir . '/postgresql.conf',
        ]);
        
        // Копируем бэкап
        `[ -f $dir/master ] && rm $dir/master`;
        `[ -f $dir/postmaster.pid ] && rm $dir/postmaster.pid`;
        `[ -f $dir/recovery.done ] && rm $dir/recovery.done`;
        
        // Сохранеям информацию для восстановления
        file_put_contents($dir . '/recovery.conf', "standby_mode = 'on'
primary_conninfo = 'host=" . HostRole::getRoleHosts(static::getHostRole('master'), true, true) .  " port=" . Taskman::getPortFor(static::getHostRole('master'), null, HostRole::getRoleHosts(static::getHostRole('master'), true, true)) . " user=" . PROJECTUSER . "'
trigger_file = '" . $dir . "/master'
restore_command = '' #'cp /var/lib/postgresql/9.0/main/archive/%f \"%p\"'
");
        
        self::setState($Request->role, ['state' => 'copy_backup']);    
        return true;
    }
    
    /**
     * Репликаци данных после успешного копирования бэкапа на слейве
     * @return true
     */
    public static function startSlaveDatabase() {
        /** @var $Request PostgresStateDaemon_GoNextState */           
        $Request = CatchRequest([static::class . '_GoNextState' => ['state' => 'copy_backup', 'role' => 'slave']]);
        echo __METHOD__;
       
        // Daemon process
        Taskman::installDaemonUnderTaskman('postgres', self::$pgdir . '/bin/postgres -p ' . Taskman::getPortFor(static::getHostRole('slave')) . ' -c config_file=' . self::getState($Request->role)['config'], true);
        

        sleep(5);
        
        // Waiting sync
        while (true) {
            $rep_delay = DB::connect(DB::getDsn(static::getHostRole('slave')))->query('select now() - pg_last_xact_replay_timestamp() AS replication_delay', PDO::FETCH_COLUMN, 0)->fetch();
            $parse = sscanf($rep_delay, '%d:%d:%d.%d');
            $delay = $parse[0] * 3600 + $parse[1] * 60 + $parse[2];
            
            if ($delay <= 3) {
                break;
            }
            
            sleep(3);
        }

        self::setState($Request->role, ['state' => 'slave_ready']);
        return true;
    }
    
    /**
     * Проверка мастера на доступность и в случае падения реагирование и изменение стейта
     * @return true
     */
    public static function slaveCheckMaster() {
        /** @var $Request PostgresStateDaemon_GoNextState */           
        $Request = CatchRequest([static::class . '_GoNextState' => ['state' => 'slave_ready', 'role' => 'slave']]);
        echo __METHOD__;
    
        if (self::getState('master')['state'] !== 'slave_init') {
            return false;
        }
        // Если мастер не пишет, переходим на стейт создания слейва мастером
        // Если слейв не отвечает, исключаем текущий хост из обихода
        // и ждем, когда мастер поднимет другой слейв
        // Query slave host
        try {
            $Slave = DB::connect(DB::getDsn(static::getHostRole('slave')));
            $ts  = $Slave->query('SELECT last_value FROM postgres_alive_seq', PDO::FETCH_COLUMN, 0)->fetch();
            
            // Replication delay
            $rep_delay = $Slave->query('select now() - pg_last_xact_replay_timestamp() AS replication_delay', PDO::FETCH_COLUMN, 0)->fetch();
            $parse = sscanf($rep_delay, '%d:%d:%d.%d');
            $delay = $parse[0] * 3600 + $parse[1] * 60 + $parse[2];
        
            if ($ts > 1 && $delay >= 30 && $ts < time() - 30) {
                self::setState($Request->role, ['state' => 'check_master']);
            }            
        } catch (Exception $E) {
            self::stopProcessing();
        }
        
        return true;
    }
    
    /**
     * Установка слейва мастером и смена ролей
     * @return true
     */
    public static function makeSlaveMaster() {
        /** @var $Request PostgresStateDaemon_GoNextState */           
        $Request = CatchRequest([static::class . '_GoNextState' => ['state' => 'check_master', 'role' => 'slave']]);
        echo __METHOD__;
        
        $master_file = self::getState($Request->role)['dir'] . '/master';
        `touch '$master_file'`;
        
        // Изменяем роли
        $host = Taskman::getHostname();
        $master_host = HostRole::getRoleHosts(static::getHostRole('master'), true, true);
        
        Log::error([
            'message' => 'Make slave master',
            'old_master_host' => $master_host,
        ], __CLASS__);
        
        echo "\nadd master $host\nremove master $master_host\nremove slave $host";
        $master_role = static::getHostRole('master');
        $slave_role  = static::getHostRole('slave');
        $pool_role   = static::getHostRole('pool');
        
        $master_role::addToHost($host);
        $master_role::removeFromHost($master_host);
        $slave_role::removeFromHost($host);
        $pool_role::addToHost($master_host);
        
        // Оставлем старый порт на роль мастера уже на текущем хосте
        Taskman::renameRole(static::getHostRole('slave'), static::getHostRole('master'));
        
        // Меняем пути до папок в хранилище состояния
        $slave = self::getState('slave');
        self::setState('slave', [
            'state'  => 'init',
            'dir'    => '',
            'config' => '',
        ]);
        
        self::setState('master', [
            'state'  => 'base_ready',
            'dir'    => $slave['dir'],
            'config' => $slave['config'],
        ]);
        
        // Extra wait for becoming slave master
        sleep(10);
        
        return true;
    }
    
    public static function stopRunningProcesses() {
        CatchEvent(static::class . '_Stopped');
        Taskman::deleteDaemonUnderTaskman('postgres');
    }
    
    public static function getDaemonInfo() {
        $master_ts = 0;
        $slave_ts  = 0;
        $rep_delay = 0;
        $delay     = 0;
        $master_up = false;
        $slave_up  = false;
        $q = 'SELECT last_value FROM postgres_alive_seq';
        
        try {
            $master_ts = DB::connect()->query($q, PDO::FETCH_COLUMN, 0)->fetch();
            $master_up = true;
        } catch (Exception $E) {}
        
        try {
            $slave_ts  = DB::connect(DB::getDsn(static::getHostRole('slave')))->query($q, PDO::FETCH_COLUMN, 0)->fetch();            
            // Replication delay
            $rep_delay = DB::connect(DB::getDsn(static::getHostRole('slave')))->query('select now() - pg_last_xact_replay_timestamp() AS replication_delay', PDO::FETCH_COLUMN, 0)->fetch();
            $parse = sscanf($rep_delay, '%d:%d:%d.%d');
            $delay = $parse[0] * 3600 + $parse[1] * 60 + $parse[2];
            $slave_up = true;
        } catch (Exception $E) {}
        
        return [
            'etcd_key_state'        => static::getState(),
            'master_hosts'          => HostRole::getRoleHosts(static::getHostRole('master')),
            'slave_hosts'           => HostRole::getRoleHosts(static::getHostRole('slave')),
            'pool_hosts'            => HostRole::getRoleHosts(static::getHostRole('pool')),
            'master_up'             => $master_up,
            'slave_up'              => $slave_up,
            'master_alive_ts'       => $master_ts,
            'slave_alive_ts'        => $slave_ts,
            'replication_delay'     => $rep_delay,
            'replication_delay_sec' => $delay,
        ];
        
    }




}


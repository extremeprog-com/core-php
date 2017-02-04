<?php
trait MasterSlaveStateDaemon {
    
    /**
     * Установка демона инициализации и контроля работы механизма мастер - слейв
     * @return	void
     */
    public static function runStateDaemon() {
        CatchEvent(
            static::getHostRole('master') . '_Add',
            static::getHostRole('slave') . '_Add',
            [ static::getHostRole('master') => \_OS\Core\System_InitDaemons::class ],
            [ static::getHostRole('slave') => \_OS\Core\System_InitDaemons::class ]
        );

        Taskman::installDaemonUnderTaskman(
            static::class . 'StateDaemon',
            'php-r "' . static::class . '::stateDaemon();" -- --rev=' . PROJECTREV
        );
    }
    
    /**
     * Демон изменения состояния
     */
    public static function stateDaemon() {
        $class = self::getGoNextStateClassName();

        for(;;) {
            FireRequest(new $class([
                'role'  => $role = self::getRole(),
                'state' => self::getState($role)['state'],
            ]));
            sleep(15);
        }
    }
   
    /**
     * Установка нового состояния
     * @param   string  $role
     * @param	arrays	$state	Состояние
     */
    public static function setState($role, array $state = []) {
        if ($host = HostRole::getRoleHosts(static::getHostRole($role), true, true)) {
            $state['host'] = $host;
        }
        if (Etcd::enabled()) {
            $etcd_key_class = static::getEtcdKeyClassName();
            $etcd_key_class::modify(
                $role,
                array_merge(self::getState($role), $state)
            );
        } else {
            $file = PATH_DATA . '/' . static::class . '.json';

            $current = self::getState();
            $current[$role] = array_merge($current[$role], $state);

            FileAPI::putJSON($file, $current);
        }
    }
    
    /**
     * Получение текущего состояния, в котором находится роль
     * @param string|null $role
     * @return	array
     */
    public static function getState($role = null) {
        $default = [
            'master' => [
                'state'     => 'init',
                'dir'       => '',
                'config'    => '',
                'host'      => '',
                'backup_ts' => 0,
            ],
            'slave' => [
                'state'  => 'init',
                'dir'    => '',
                'config' => '',
            ]
        ];

        if (Etcd::enabled()) {
            $etcd_key_class = self::getEtcdKeyClassName();            
            $state = $etcd_key_class::get();
        } else {
            $file = PATH_DATA . '/' . static::class . '.json';
            $state = FileAPI::getJSON($file);
        }

        $state = array_merge($default, $state);

        return $role ? $state[$role] : $state;
    }
    
    public static function getNextInstance() {


        if (Etcd::enabled()) {
            $etcd_key_class = self::getEtcdKeyClassName();
            if (!$etcd_key_class::get()) {
                $etcd_key_class::set(self::getState());
            }
            return $etcd_key_class::increment();
        } else {
            $state = self::getState();
            if (!isset($state['instance'])) {
                $state['instance'] = 0;
            }
            ++$state['instance'];
            FileAPI::putJSON(PATH_DATA . '/' . static::class . '.json', $state);
            return $state['instance'];
        }
    }
    
    /**
     * Получение текущей ролиx
     * @return	string	
     */
    public static function getRole() {
        $host = Taskman::getHostname();
        $master_host = HostRole::getRoleHosts(static::getHostRole('master'), true, true);
        $slave_host = HostRole::getRoleHosts(static::getHostRole('slave'), true, true);
        
        if ($host === $master_host) {
            return 'master';
        }
        
        if ($host === $slave_host) {
            return 'slave';
        }
        
        self::stopProcessing();
        
        throw new Exception('No state for current host. Daemon removed.');
    }
    
    /**
     * Исключение текущего хоста, остановка всех демонов и т.п.
     */
    protected static function stopProcessing() {
        Log::error([
            'message' => 'Stopping state daemon on host',
        ], static::class);
        Taskman::deleteDaemonUnderTaskman(static::class . 'StateDaemon');
        $event_name = static::class . '_Stopped';
        FireEvent(new $event_name);
    }
    
    
    public static function getGoNextStateClassName() {
        return static::class . '_GoNextState';
    }
    
    public static function getEtcdKeyClassName() {
        return static::class . 'EtcdKey';
    }
    
    /**
     * Создание файлика перехода в другое состояние
     * @return	object		Description
     */
    public static function _createGoNextStateClass() {
        CatchEvent(\_OS\Core\System_InitFiles::class);
        
        $class_name = self::getGoNextStateClassName();
        $class_file = PROJECTTMP . '/' . $class_name . '.php';
        
        $template = <<<EOF
<?php
class $class_name extends \_OS\Request {
    public \$state;
    public \$role;
    
    public function __construct(array \$fields = []) {
        foreach (\$fields as \$key => \$val) {
            \$this->\$key = \$val;
        }
    }    
}
EOF;
        file_put_contents($class_file, $template);
    }
    
    /**
     * Создание файлика хранения ключа етцд состояния
     * @return	object		Description
     */
    public static function _createEtcdKeyClass() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        $class_name = self::getEtcdKeyClassName();
        $class_file = PROJECTTMP . '/' . $class_name . '.php';
        $template = <<<EOF
<?php
class $class_name extends EtcdKey {
    
    /**
     * Инкремент номера инстанса
     * @param	int	\$count
     * @return	int     Новое установленное значение
     */
    public static function increment(\$count = 1) {
        \$state = static::get();
        \$state['instance'] = isset(\$state['instance']) ? \$state['instance'] + \$count : \$count;
        static::set(\$state);
        return \$state['instance'];
    }    
}
EOF;
        file_put_contents($class_file, $template);
    }
    
    /**
     * Проверка доступности сервера по порту
     * @param	string	$host	
     * @param	int	$port	
     * @return	bool
     */
    public static function checkSocket($host, $port) {
        try {
             if (!$f = fsockopen($host, $port)) {
                 return false;
             }
        } catch (Exception $E) {
            return false;
        }
        fclose($f);
        return true;
    }
    
    /**
     * Генерация классов с необходимыми ролями
     */
    public static function _generateHostRoles() {
        CatchEvent(\_OS\Core\System_InitFiles::class);

        foreach (static::$roles as $key => $value) {
            $class_name = static::getHostRole($key);
            $content = <<<EOF
<?php
class {$class_name} extends HostRole {
        
}
EOF;
            file_put_contents(PROJECTTMP . '/' . $class_name . '.php', $content);
        }
    }

    /**
     * Генерация события изменения мастера
     */
    public static function _generateMasterChangedEvent() {
        CatchEvent(\_OS\Core\System_InitFiles::class);
        
        $class_name = static::class . '_MasterChanged';
        $content = <<<EOF
<?php
class {$class_name} extends \_OS\Event {
}
EOF;
        file_put_contents(PROJECTTMP . '/' . $class_name . '.php', $content);
    
    }
    
    /**
     * Генерация события остановки демона
     */
    public static function _generateDaemonStoppedEvent() {
        CatchEvent(\_OS\Core\System_InitFiles::class);
        
        $class_name = static::class . '_Stopped';
        $content = <<<EOF
<?php
class {$class_name} extends \_OS\Event {
}
EOF;
        file_put_contents(PROJECTTMP . '/' . $class_name . '.php', $content);
    
    }
    
    /**
     * Получение хоста по ключу
     * @param	string	$key	
     * @return	string|null
     */
    public static function getHostRole($key) {
        return isset(static::$roles[$key]) ? static::class . static::$roles[$key] : null;
    }
    
    /**
     * Выполнение бэкапа под таскманом, после завершения удаление таска
     */
    public static function backup() {
        $state = self::getState('master');
        Taskman::deleteDaemonUnderTaskman(static::class . 'Backup');
        if (isset(static::$backup) && static::$backup) {
            Backup::make($state['dir'], static::class);
        }
    }
    
    /**
     * Создание ежедневного бэкапа согласно текущему стейту, вызывает мастер
     */
    public static function makeDailyBackup() {
        $state = self::getState('master');
        
        // Делаем бэкап каждый день после 4х утра
        if (date('Ymd', $state['backup_ts']) < date('Ymd') && date('H') >= 4) {
            Log::info([
                'message' => 'Making backup data',
            ], static::class);
            
            Taskman::installDaemonUnderTaskman(static::class . 'Backup', 'php-r "' . static::class . '::backup()"');
            self::setState('master', ['backup_ts' => time()]);            
        }
    }    
}
